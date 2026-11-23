(async () => {
  const SYNC_INTERVAL_MS = 20 * 60 * 60 * 1000; // 20 timer

  const settings = await chrome.storage.local.get([
    "facultyId",
    "weeks",
    "lastAutoSync",
    "skipMotetid",
    "includeLessons",
    "includeActivities",
  ]);

  if (!settings.facultyId) return;

  const now = Date.now();
  if (settings.lastAutoSync && now - settings.lastAutoSync < SYNC_INTERVAL_MS) {
    return;
  }

  // Sjekk om lokal bro (local_bridge.py) kjører
  try {
    const ping = await fetch("http://127.0.0.1:8765/ping");
    if (!ping.ok) return;
  } catch (_) {
    return;
  }

  const numWeeks = Number(settings.weeks || 2);
  const getMonday = (date) => {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    d.setDate(diff);
    return d;
  };
  const formatDDMMYYYY = (date) => {
    const dd = String(date.getDate()).padStart(2, "0");
    const mm = String(date.getMonth() + 1).padStart(2, "0");
    const yyyy = date.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
  };

  try {
    const startMonday = getMonday(new Date());
    const allItems = [];

    for (let i = 0; i < numWeeks; i++) {
      const d = new Date(startMonday);
      d.setDate(d.getDate() + i * 7);
      const forWeek = formatDDMMYYYY(d);
      const url = `${window.location.origin}/control/timetablev2/faculty/${settings.facultyId}/fetch/current?forWeek=${encodeURIComponent(forWeek)}&only-learner-published-exam-groups=true&extra-info=true&presence=true&types=LESSON,EVENT,ACTIVITY,SUBSTITUTION&_=${Date.now()}`;
      const resp = await fetch(url, { credentials: "include" });
      if (!resp.ok) return; // Ikke innlogget ennå
      const data = await resp.json();
      if (Array.isArray(data.timetableItems)) {
        allItems.push(...data.timetableItems);
      }
    }

    const includeLessons = settings.includeLessons !== false;
    const includeActivities = settings.includeActivities !== false;
    const filteredItems = allItems.filter((item) => {
      const type = item.type || "LESSON";
      if (type === "LESSON" && !includeLessons) return false;
      if ((type === "ACTIVITY" || type === "EVENT") && !includeActivities) return false;
      if (settings.skipMotetid && (item.label || "").startsWith("Møtetid")) return false;
      return true;
    });

    if (filteredItems.length === 0) return;

    const endSunday = new Date(startMonday);
    endSunday.setDate(endSunday.getDate() + numWeeks * 7 - 1);

    const payload = filteredItems.map((item) => ({
      entityId: String(item.entityId || ""),
      title: item.subject ? `${item.label} (${item.subject.split("/")[0]})` : item.label || "Timeplan",
      start: `${item.date.split("/").reverse().join("-")}T${item.startTime}:00`,
      end: `${item.date.split("/").reverse().join("-")}T${item.endTime}:00`,
      location: item.mainRoom || (item.locations && item.locations[0]) || "",
      type: item.type || "LESSON",
    }));

    const resp = await fetch("http://127.0.0.1:8765/save-inschool", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        rangeStart: `${startMonday.toISOString().slice(0, 10)}T00:00:00Z`,
        rangeEnd: `${endSunday.toISOString().slice(0, 10)}T23:59:59Z`,
        events: payload,
      }),
    });

    if (resp.ok) {
      let createdInInschool = 0;
      try {
        const outlookResp = await fetch("http://127.0.0.1:8765/outlook-events");
        if (outlookResp.ok) {
          const paData = await outlookResp.json();
          const outlookEvents = Array.isArray(paData) ? paData : paData.value || paData.events || [];
          const existingKeys = new Set(
            allItems.map((it) => {
              const isoDate = (it.date || "").split("/").reverse().join("-");
              return `${isoDate}|${it.startTime}|${(it.label || "").trim().toLowerCase()}`;
            })
          );
          const toOsloDateTime = (isoStr) => {
            const utcStr = isoStr.endsWith("Z") || isoStr.includes("+") ? isoStr : `${isoStr}Z`;
            const formatted = new Date(utcStr).toLocaleString("sv-SE", { timeZone: "Europe/Oslo" });
            return { date: formatted.slice(0, 10), time: formatted.slice(11, 16) };
          };
          const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
          const xsrf = m ? decodeURIComponent(m[1]) : "";
          for (const ev of outlookEvents) {
            const subject = (ev.subject || ev.Subject || "").trim().slice(0, 50);
            const startRaw = ev.startWithTimeZone || ev.start || ev.Start || "";
            const endRaw = ev.endWithTimeZone || ev.end || ev.End || "";
            if (!subject || !startRaw || !endRaw) continue;
            const s = toOsloDateTime(startRaw);
            const e = toOsloDateTime(endRaw);
            if (existingKeys.has(`${s.date}|${s.time}|${subject.toLowerCase()}`)) continue;
            const createResp = await fetch(`${window.location.origin}/control/events?_=${Date.now()}`, {
              method: "POST",
              credentials: "include",
              headers: { "Content-Type": "application/json", "X-Xsrf-Token": xsrf },
              body: JSON.stringify({
                name: subject,
                description: "Synkronisert fra Outlook",
                date: s.date,
                startTime: s.time,
                endTime: e.time,
                includeCreator: false,
                learnersId: [],
                requiredAttendance: false,
                roomsId: [],
                teachersId: [Number(settings.facultyId)],
              }),
            });
            if (createResp.ok) createdInInschool++;
          }
        }
      } catch (_) {}

      await chrome.storage.local.set({
        lastAutoSync: now,
        lastAutoSyncSummary: { toOutlook: payload.length, toInschool: createdInInschool },
      });
      chrome.runtime.sendMessage({
        type: "autoCalendarSyncDone",
        toOutlook: payload.length,
        toInschool: createdInInschool,
      }).catch(() => {});
      console.log(`[InSchool Sync] Automatisk synkronisert ${payload.length} til Outlook, ${createdInInschool} til InSchool.`);
      const toast = document.createElement("div");
      toast.textContent = `Auto-sync ferdig (${payload.length} → Outlook, ${createdInInschool} → InSchool)`;
      Object.assign(toast.style, {
        position: "fixed",
        bottom: "20px",
        right: "20px",
        background: "#059669",
        color: "#fff",
        padding: "10px 16px",
        borderRadius: "8px",
        fontSize: "13px",
        fontWeight: "600",
        boxShadow: "0 4px 12px rgba(0,0,0,0.2)",
        zIndex: "999999",
        fontFamily: "-apple-system, BlinkMacSystemFont, sans-serif",
      });
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 3500);
    }
  } catch (err) {
    console.warn("[InSchool Sync] Auto-sync feilet:", err);
  }
})();
