const weeksEl = document.getElementById("weeks");
const facultyIdEl = document.getElementById("facultyId");
const skipMotetidEl = document.getElementById("skipMotetid");
const includeLessonsEl = document.getElementById("includeLessons");
const includeActivitiesEl = document.getElementById("includeActivities");
const btnIcs = document.getElementById("btn-ics");
const btnOnedrive = document.getElementById("btn-onedrive");
const btnToInschool = document.getElementById("btn-to-inschool");
const outlookJsonFileEl = document.getElementById("outlookJsonFile");
const statusEl = document.getElementById("status");
const autosyncBadgeEl = document.getElementById("autosync-badge");

// Sjekk om lokal bro for auto-sync kjører
fetch("http://127.0.0.1:8765/ping")
  .then((r) => {
    if (r.ok) autosyncBadgeEl.style.display = "block";
  })
  .catch(() => {});

// Nullstill badge når popup åpnes
if (chrome.action && chrome.action.setBadgeText) {
  chrome.action.setBadgeText({ text: "" });
}

// Last lagrede innstillinger, eller hent Lærer-ID automatisk fra aktiv InSchool-fane
chrome.storage.local.get(["facultyId", "weeks", "skipMotetid", "includeLessons", "includeActivities", "lastAutoSync", "lastAutoSyncSummary"], async (res) => {
  if (res.lastAutoSync) {
    const t = new Date(res.lastAutoSync).toLocaleTimeString("nb-NO", { hour: "2-digit", minute: "2-digit" });
    const s = res.lastAutoSyncSummary;
    const summary = s ? ` (${s.toOutlook} → Outlook, ${s.toInschool} → InSchool)` : "";
    setStatus(`Sist auto-synk: kl. ${t}${summary}`, "ok");
  }
  if (res.facultyId) facultyIdEl.value = res.facultyId;
  if (res.weeks) weeksEl.value = res.weeks;
  if (res.skipMotetid) skipMotetidEl.checked = true;
  if (res.includeLessons !== undefined) includeLessonsEl.checked = res.includeLessons;
  if (res.includeActivities !== undefined) includeActivitiesEl.checked = res.includeActivities;

  if (!res.facultyId) {
    try {
      const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
      if (tab && tab.url && tab.url.includes("inschool.visma.no")) {
        const [{ result }] = await chrome.scripting.executeScript({
          target: { tabId: tab.id },
          func: () => {
            const urls = [window.location.href, ...performance.getEntriesByType("resource").map((r) => r.name)];
            for (const u of urls) {
              const m = u.match(/\/faculty\/(\d+)\//);
              if (m) return m[1];
            }
            return null;
          },
        });
        if (result) {
          facultyIdEl.value = result;
          chrome.storage.local.set({ facultyId: result });
        }
      }
    } catch (_) {}
  }
});

function saveSettings() {
  chrome.storage.local.set({
    facultyId: facultyIdEl.value.trim(),
    weeks: weeksEl.value,
    skipMotetid: skipMotetidEl.checked,
    includeLessons: includeLessonsEl.checked,
    includeActivities: includeActivitiesEl.checked,
  });
}

function filterItems(rawItems) {
  return rawItems.filter((item) => {
    const type = item.type || "LESSON";
    if (type === "LESSON" && !includeLessonsEl.checked) return false;
    if ((type === "ACTIVITY" || type === "EVENT") && !includeActivitiesEl.checked) return false;
    if (skipMotetidEl.checked && (item.label || "").startsWith("Møtetid")) return false;
    return true;
  });
}

function showStatus(msg, type = "info") {
  statusEl.textContent = msg;
  statusEl.className = type;
}

function getMonday(date) {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1);
  d.setDate(diff);
  return d;
}

function formatDDMMYYYY(date) {
  const dd = String(date.getDate()).padStart(2, "0");
  const mm = String(date.getMonth() + 1).padStart(2, "0");
  const yyyy = date.getFullYear();
  return `${dd}/${mm}/${yyyy}`;
}

async function fetchTimetableFromActiveTab(facultyId, numWeeks) {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (!tab || !tab.url || !tab.url.includes("inschool.visma.no")) {
    throw new Error("Åpne Visma InSchool i den aktive fanen først.");
  }

  const origin = new URL(tab.url).origin;
  const startMonday = getMonday(new Date());
  const weekDates = [];
  for (let i = 0; i < numWeeks; i++) {
    const d = new Date(startMonday);
    d.setDate(d.getDate() + i * 7);
    weekDates.push(formatDDMMYYYY(d));
  }

  const results = await chrome.scripting.executeScript({
    target: { tabId: tab.id },
    func: async (baseOrigin, facId, dates) => {
      const allItems = [];
      for (const forWeek of dates) {
        const url = `${baseOrigin}/control/timetablev2/faculty/${facId}/fetch/current?forWeek=${encodeURIComponent(forWeek)}&only-learner-published-exam-groups=true&extra-info=true&presence=true&types=LESSON,EVENT,ACTIVITY,SUBSTITUTION&_=${Date.now()}`;
        const resp = await fetch(url, { credentials: "include" });
        if (!resp.ok) {
          throw new Error(`HTTP ${resp.status} for uke ${forWeek}`);
        }
        const data = await resp.json();
        if (Array.isArray(data.timetableItems)) {
          allItems.push(...data.timetableItems);
        }
      }
      return allItems;
    },
    args: [origin, facultyId, weekDates],
  });

  return results[0].result;
}

function toIcsDateTime(dateStr, timeStr) {
  // dateStr: "23/11/2026", timeStr: "09:50" -> "20261123T095000"
  const [dd, mm, yyyy] = dateStr.split("/");
  const [hh, min] = timeStr.split(":");
  return `${yyyy}${mm}${dd}T${hh}${min}00`;
}

function escapeIcs(text) {
  return (text || "")
    .replace(/\\/g, "\\\\")
    .replace(/;/g, "\\;")
    .replace(/,/g, "\\,")
    .replace(/\n/g, "\\n");
}

function buildIcs(items) {
  const lines = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//InSchool Kalender Sync//NO",
    "CALSCALE:GREGORIAN",
    "X-WR-CALNAME:InSchool Timeplan",
    "X-WR-TIMEZONE:Europe/Oslo",
  ];

  const nowStamp = new Date().toISOString().replace(/[-:]/g, "").split(".")[0] + "Z";

  for (const item of items) {
    if (!item.date || !item.startTime || !item.endTime) continue;

    const title = item.subject
      ? `${item.label} (${item.subject.split("/")[0]})`
      : item.label || "Timeplan";
    const location = item.mainRoom || (item.locations && item.locations[0]) || "";
    const uid = `inschool-${item.entityId || `${item.date}-${item.startTime}`}@visma.no`;

    lines.push("BEGIN:VEVENT");
    lines.push(`UID:${uid}`);
    lines.push(`DTSTAMP:${nowStamp}`);
    lines.push(`DTSTART;TZID=Europe/Oslo:${toIcsDateTime(item.date, item.startTime)}`);
    lines.push(`DTEND;TZID=Europe/Oslo:${toIcsDateTime(item.date, item.endTime)}`);
    lines.push(`SUMMARY:${escapeIcs(title)}`);
    if (location) lines.push(`LOCATION:${escapeIcs(location)}`);
    lines.push(`DESCRIPTION:${escapeIcs(`Type: ${item.type || "LESSON"}\nFagkode: ${item.subjectCode || "-"}`)}`);
    lines.push("END:VEVENT");
  }

  lines.push("END:VCALENDAR");
  return lines.join("\r\n");
}

btnIcs.addEventListener("click", async () => {
  saveSettings();
  btnIcs.disabled = true;
  showStatus("Henter timeplan fra InSchool...", "info");

  try {
    const rawItems = await fetchTimetableFromActiveTab(facultyIdEl.value.trim(), Number(weeksEl.value));
    const items = filterItems(rawItems);
    const ics = buildIcs(items);
    const blob = new Blob([ics], { type: "text/calendar;charset=utf-8" });
    const url = URL.createObjectURL(blob);

    const a = document.createElement("a");
    a.href = url;
    a.download = `inschool-timeplan-${new Date().toISOString().slice(0, 10)}.ics`;
    a.click();
    URL.revokeObjectURL(url);

    showStatus(`Ferdig! Lastet ned ${items.length} timer/aktiviteter.`, "ok");
  } catch (err) {
    showStatus(`Feil: ${err.message}`, "err");
  } finally {
    btnIcs.disabled = false;
  }
});

btnOnedrive.addEventListener("click", async () => {
  saveSettings();
  btnOnedrive.disabled = true;
  showStatus("Henter timeplan fra InSchool...", "info");

  try {
    const rawItems = await fetchTimetableFromActiveTab(facultyIdEl.value.trim(), Number(weeksEl.value));
    const items = filterItems(rawItems);

    const payload = items.map((item) => ({
      entityId: String(item.entityId || ""),
      title: item.subject ? `${item.label} (${item.subject.split("/")[0]})` : item.label || "Timeplan",
      start: `${item.date.split("/").reverse().join("-")}T${item.startTime}:00`,
      end: `${item.date.split("/").reverse().join("-")}T${item.endTime}:00`,
      location: item.mainRoom || (item.locations && item.locations[0]) || "",
      type: item.type || "LESSON",
    }));

    const startMonday = getMonday(new Date());
    const endSunday = new Date(startMonday);
    endSunday.setDate(endSunday.getDate() + Number(weeksEl.value) * 7 - 1);

    const json = JSON.stringify(
      {
        rangeStart: `${startMonday.toISOString().slice(0, 10)}T00:00:00Z`,
        rangeEnd: `${endSunday.toISOString().slice(0, 10)}T23:59:59Z`,
        events: payload,
      },
      null,
      2
    );

    const filename = `inschool-sync-${new Date().toISOString().slice(0, 10)}.json`;
    if (window.showSaveFilePicker) {
      const handle = await window.showSaveFilePicker({
        id: "inschool-onedrive",
        suggestedName: filename,
        types: [{ description: "JSON", accept: { "application/json": [".json"] } }],
      });
      const writable = await handle.createWritable();
      await writable.write(json);
      await writable.close();
    } else {
      const blob = new Blob([json], { type: "application/json;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      a.click();
      URL.revokeObjectURL(url);
    }

    showStatus(`Lagret ${payload.length} hendelser (.json) til OneDrive-mappen!`, "ok");
  } catch (err) {
    showStatus(`Feil: ${err.message}`, "err");
  } finally {
    btnOnedrive.disabled = false;
  }
});

btnToInschool.addEventListener("click", () => {
  outlookJsonFileEl.value = "";
  outlookJsonFileEl.click();
});

outlookJsonFileEl.addEventListener("change", async () => {
  const file = outlookJsonFileEl.files[0];
  if (!file) return;

  saveSettings();
  const facId = facultyIdEl.value.trim();
  if (!facId) {
    showStatus("Mangler Lærer-ID.", "err");
    return;
  }

  btnToInschool.disabled = true;
  showStatus("Leser Outlook-fil og sjekker InSchool...", "info");

  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab || !tab.url || !tab.url.includes("inschool.visma.no")) {
      throw new Error("Åpne Visma InSchool i den aktive fanen først.");
    }

    const text = await file.text();
    const paData = JSON.parse(text);
    const outlookEvents = Array.isArray(paData) ? paData : paData.value || paData.events || [];

    const numWeeks = Number(weeksEl.value);

    const existingItems = await fetchTimetableFromActiveTab(facId, numWeeks);
    const existingKeys = new Set(
      existingItems.map((it) => {
        const isoDate = (it.date || "").split("/").reverse().join("-");
        return `${isoDate}|${it.startTime}|${(it.label || "").trim().toLowerCase()}`;
      })
    );

    const toOsloDateTime = (isoStr) => {
      const utcStr = isoStr.endsWith("Z") || isoStr.includes("+") ? isoStr : `${isoStr}Z`;
      const formatted = new Date(utcStr).toLocaleString("sv-SE", { timeZone: "Europe/Oslo" });
      return { date: formatted.slice(0, 10), time: formatted.slice(11, 16) };
    };

    const toCreate = [];
    let skipped = 0;
    for (const ev of outlookEvents) {
      const subject = (ev.subject || ev.Subject || "").trim().slice(0, 50);
      const startRaw = ev.startWithTimeZone || ev.start || ev.Start || "";
      const endRaw = ev.endWithTimeZone || ev.end || ev.End || "";
      if (!subject || !startRaw || !endRaw) continue;

      const s = toOsloDateTime(startRaw);
      const e = toOsloDateTime(endRaw);
      const key = `${s.date}|${s.time}|${subject.toLowerCase()}`;

      if (existingKeys.has(key)) {
        skipped++;
        continue;
      }
      toCreate.push({ name: subject, date: s.date, startTime: s.time, endTime: e.time });
    }

    if (toCreate.length === 0) {
      showStatus(`Ingen nye møter å opprette (${skipped} fantes allerede).`, "ok");
      return;
    }

    const origin = new URL(tab.url).origin;
    const xsrfCookie = await chrome.cookies.get({ url: origin, name: "XSRF-TOKEN" });
    const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.value) : "";

    const [{ result: createdCount }] = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      func: async (baseOrigin, teacherId, eventsList, xsrf) => {
        let count = 0;
        for (const ev of eventsList) {
          const resp = await fetch(`${baseOrigin}/control/events?_=${Date.now()}`, {
            method: "POST",
            credentials: "include",
            headers: {
              "Content-Type": "application/json",
              "X-Xsrf-Token": xsrf,
            },
            body: JSON.stringify({
              name: ev.name,
              description: "Synkronisert fra Outlook",
              date: ev.date,
              startTime: ev.startTime,
              endTime: ev.endTime,
              includeCreator: false,
              learnersId: [],
              requiredAttendance: false,
              roomsId: [],
              teachersId: [Number(teacherId)],
            }),
          });
          if (resp.ok) count++;
        }
        return count;
      },
      args: [origin, facId, toCreate, xsrfToken],
    });

    showStatus(`Opprettet ${createdCount} avtaler i InSchool (${skipped} hoppet over).`, "ok");
  } catch (err) {
    showStatus(`Feil: ${err.message}`, "err");
  } finally {
    btnToInschool.disabled = false;
  }
});
