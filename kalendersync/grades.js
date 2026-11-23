(() => {
  const tabKalenderBtn = document.getElementById("tab-kalender");
  const tabKaraktererBtn = document.getElementById("tab-karakterer");
  const viewKalender = document.getElementById("view-kalender");
  const viewKarakterer = document.getElementById("view-karakterer");

  const gradeFileEl = document.getElementById("gradeFile");
  const btnPickGradeFile = document.getElementById("btn-pick-grade-file");
  const assignmentSelectEl = document.getElementById("grade-assignment");
  const multiWrapEl = document.getElementById("grade-multi-wrap");
  const assignmentWrapEl = document.getElementById("grade-assignment-wrap");
  const previewWrapEl = document.getElementById("grade-preview-wrap");
  const previewBodyEl = document.getElementById("grade-preview-body");
  const gradeScaleSelect = document.getElementById("grade-scale");
  const createNewCheckbox = document.getElementById("grade-create-new");
  const btnSaveGrades = document.getElementById("btn-save-grades");
  const btnRefreshInschool = document.getElementById("btn-refresh-inschool");
  const gradeStatusEl = document.getElementById("grade-status");

  let parsedByAssignment = {};
  let assignmentScales = {};
  let lastJsonText = null;
  let currentInschoolCtx = null;
  let matchedRows = [];

  function setGradeStatus(msg, type = "info") {
    gradeStatusEl.textContent = msg;
    gradeStatusEl.className = type;
  }

  tabKalenderBtn.addEventListener("click", () => {
    tabKalenderBtn.classList.add("active");
    tabKaraktererBtn.classList.remove("active");
    viewKalender.style.display = "block";
    viewKarakterer.style.display = "none";
  });

  async function checkActiveInschoolTab() {
    if (Object.keys(parsedByAssignment).length > 0) return;
    try {
      const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
      if (!tab || !tab.url || !tab.url.includes("inschool.visma.no")) {
        setGradeStatus("Åpne en vurdering i Visma InSchool i den aktive fanen før du synkroniserer.", "info");
        return;
      }
      const gm = tab.url.match(/\/(?:gradebook|group)\/(\d+)/);
      const am = tab.url.match(/\/details\/(\d+)/);
      if (gm && am) {
        setGradeStatus(`✓ Klar for InSchool (Gruppe ${gm[1]}, vurdering ${am[1]}). Velg fil over.`, "ok");
      } else if (gm) {
        setGradeStatus(`Fant gruppe ${gm[1]} i InSchool – åpne selve vurderingen (details) før du lagrer.`, "info");
      } else {
        setGradeStatus("Åpne en vurdering i Visma InSchool før du velger fil.", "info");
      }
    } catch (_) {}
  }

  tabKaraktererBtn.addEventListener("click", () => {
    tabKaraktererBtn.classList.add("active");
    tabKalenderBtn.classList.remove("active");
    viewKalender.style.display = "none";
    viewKarakterer.style.display = "block";
    checkActiveInschoolTab();
  });

  function parseCsv(text) {
    const firstLines = text.split(/\r?\n/).slice(0, 3).join("\n");
    const delim = (firstLines.match(/;/g) || []).length > (firstLines.match(/,/g) || []).length ? ";" : ",";
    const rows = [];
    let row = [];
    let field = "";
    let inQuotes = false;
    for (let i = 0; i < text.length; i++) {
      const ch = text[i];
      if (inQuotes) {
        if (ch === '"' && text[i + 1] === '"') {
          field += '"';
          i++;
        } else if (ch === '"') {
          inQuotes = false;
        } else {
          field += ch;
        }
      } else if (ch === '"') {
        inQuotes = true;
      } else if (ch === delim) {
        row.push(field.trim());
        field = "";
      } else if (ch === "\n" || ch === "\r") {
        if (ch === "\r" && text[i + 1] === "\n") i++;
        row.push(field.trim());
        rows.push(row);
        row = [];
        field = "";
      } else {
        field += ch;
      }
    }
    if (field.length > 0 || row.length > 0) {
      row.push(field.trim());
      rows.push(row);
    }
    return rows;
  }

  function extractTeamsGrade(feedback) {
    const fb = (feedback || "").trim();
    if (!fb) return "";
    if (/^Vurdering:\s*Godkjent/i.test(fb)) return "GK";
    if (/^Vurdering:\s*Ikke godkjent/i.test(fb)) return "IGK";
    if (/^(?:Vurdering|Måloppnåelse):\s*Høy/i.test(fb)) return "Høy";
    if (/^(?:Vurdering|Måloppnåelse):\s*Middels/i.test(fb)) return "Middels";
    if (/^(?:Vurdering|Måloppnåelse):\s*Lav/i.test(fb)) return "Lav";
    if (/^IV\b/i.test(fb)) return "IV";
    const m = fb.match(/^Karakter:\s*([1-6](?:[+-]|\/[1-6])?|IV)/i);
    if (m) return m[1].toUpperCase();
    return "";
  }

  function parseTeamsRows(rows) {
    const headerIdx = rows.findIndex((r) => r.includes("Full Name") && r.includes("Assignments"));
    if (headerIdx !== -1) {
      const headers = rows[headerIdx];
      const nameCol = headers.indexOf("Full Name");
      const assignCol = headers.indexOf("Assignments");
      const dueCol = headers.indexOf("Due Date");
      const fbCol = headers.indexOf("Feedback");
      const grouped = {};
      for (let i = headerIdx + 1; i < rows.length; i++) {
        const r = rows[i];
        const student = (r[nameCol] || "").trim();
        const assignment = (r[assignCol] || "").trim();
        const grade = extractTeamsGrade(r[fbCol]);
        if (!student || !assignment || !grade) continue;
        if (!grouped[assignment]) grouped[assignment] = [];
        grouped[assignment].push({ student, grade, date: dueCol !== -1 ? (r[dueCol] || "").trim() || null : null });
      }
      for (const [ass, list] of Object.entries(grouped)) {
        if (list.some((x) => x.grade === "GK" || x.grade === "IGK")) assignmentScales[ass] = "Godkjent/Ikke godkjent";
        else if (list.some((x) => x.grade === "Høy" || x.grade === "Middels" || x.grade === "Lav")) assignmentScales[ass] = "Måloppnåelse";
        else assignmentScales[ass] = "Findelt med IV";
      }
      return grouped;
    }

    const gradebookIdx = rows.findIndex((r) => r.includes("First Name") && r.includes("Last Name") && r.includes("Feedback"));
    if (gradebookIdx !== -1) {
      const headers = rows[gradebookIdx];
      const firstIdx = headers.indexOf("First Name");
      const lastIdx = headers.indexOf("Last Name");
      const grouped = {};
      for (let c = 0; c < headers.length; c++) {
        if (headers[c] !== "Feedback" || c < 2) continue;
        const assignment = (headers[c - 2] || "").trim();
        if (!assignment) continue;
        for (let rIdx = gradebookIdx + 1; rIdx < rows.length; rIdx++) {
          const r = rows[rIdx];
          if (!r || r.length <= c) continue;
          const student = `${r[firstIdx] || ""} ${r[lastIdx] || ""}`.trim().replace(/\s+/g, " ");
          const grade = extractTeamsGrade(r[c]);
          if (!student || !grade) continue;
          if (!grouped[assignment]) grouped[assignment] = [];
          grouped[assignment].push({ student, grade });
        }
      }
      for (const [ass, list] of Object.entries(grouped)) {
        if (list.some((x) => x.grade === "GK" || x.grade === "IGK")) assignmentScales[ass] = "Godkjent/Ikke godkjent";
        else if (list.some((x) => x.grade === "Høy" || x.grade === "Middels" || x.grade === "Lav")) assignmentScales[ass] = "Måloppnåelse";
        else assignmentScales[ass] = "Findelt med IV";
      }
      return grouped;
    }

    throw new Error(`Fant ikke Teams-kolonnene (rader=${rows.length}, rad1=${JSON.stringify((rows[0] || []).slice(0, 5))}, rad2=${JSON.stringify((rows[1] || []).slice(0, 5))}).`);
  }

  const STANDARD_GRADE_MAP = {
    15: "6", 14.5: "6-", 14: "6/5", 13.5: "5/6", 13: "5+", 12.5: "5", 12: "5-",
    11.5: "5/4", 11: "4/5", 10.5: "4+", 10: "4", 9.5: "4-", 9: "4",
    8.5: "3/4", 8: "3+", 7.5: "3", 7: "3-", 6.5: "3/2", 6: "2/3",
    5.5: "2+", 5: "2", 4.5: "2-", 4: "2/1", 3.5: "1/2",
  };

  function calculateGrade1To6(points, maxPoints) {
    if (!maxPoints || maxPoints === 0) return "1";
    const scaled = (points / maxPoints) * 15;
    const rounded = Math.round(scaled * 2) / 2;
    if (rounded >= 15) return "6";
    if (rounded < 3.5) return "1";
    return STANDARD_GRADE_MAP[String(rounded)] || "1";
  }

  function calculateGradeByScale(points, maxPoints, overrideScale) {
    const scale = overrideScale || (gradeScaleSelect ? gradeScaleSelect.value : "Findelt med IV");
    if (scale === "Måloppnåelse") {
      if (!maxPoints || maxPoints === 0) return "Lav";
      const pct = (points / maxPoints) * 100;
      if (pct < 40) return "Lav";
      if (pct < 80) return "Middels";
      return "Høy";
    }
    if (scale === "Godkjent/Ikke godkjent") {
      if (!maxPoints || maxPoints <= 0) return "IV";
      return (points / maxPoints) * 100 >= 40 ? "GK" : "IGK";
    }
    return calculateGrade1To6(points, maxPoints);
  }

  function mapGradingModeToScale(mode) {
    const m = String(mode || "").toLowerCase();
    if (m === "level" || m === "goal" || m === "måloppnåelse") return "Måloppnåelse";
    if (m === "passfail" || m === "pass_fail" || m === "godkjent") return "Godkjent/Ikke godkjent";
    if (m === "grade" || m === "1-6" || m === "findelt") return "Findelt med IV";
    return null;
  }

  function parseCuriosaJson(text) {
    const data = JSON.parse(text);
    const subs = Array.isArray(data.submissions) ? data.submissions : [];
    const topicModes = data.topicGradingModes || {};
    const grouped = {};
    for (const s of subs) {
      const student = (s.studentFolderName || s.studentName || "").trim();
      const assignment = (s.topicTitle || "Curiosa-prøve").trim();
      const detectedScale = mapGradingModeToScale(s.gradingMode || topicModes[assignment]);
      if (detectedScale && !assignmentScales[assignment]) {
        assignmentScales[assignment] = detectedScale;
      }
      const activeScale = assignmentScales[assignment] || detectedScale;
      let grade = String(
        s.manualGrade || (s.progress && s.progress.manualGrade) || s.grade || s.finalGrade || s.teacherGrade || ""
      ).trim();
      if (!grade && s.isIV) grade = "IV";
      if (!grade && s.totalMaxScore > 0 && s.totalScore != null) {
        grade = calculateGradeByScale(Number(s.totalScore), Number(s.totalMaxScore), activeScale);
      }
      if (!grade && s.progress && s.progress.totalMaxScore > 0 && s.progress.score != null) {
        const reflVals = Object.values((s.progress.reflectionScores) || {}).map(Number).filter((n) => !Number.isNaN(n));
        const reflSum = reflVals.reduce((a, b) => a + b, 0);
        const reflMax = Number(s.reflectionMaxScore || (s.payload && s.payload.reflectionMaxScore) || 0);
        const tot = Number(s.progress.score) + reflSum;
        const max = Number(s.progress.totalMaxScore) + (reflVals.length > 0 ? reflMax : 0);
        if (max > 0) grade = calculateGradeByScale(tot, max, activeScale);
      }
      if (!grade && s.payload && s.payload.autoMaxScore > 0) {
        const hasRefl = s.payload.reflectionScore != null && s.payload.reflectionMaxScore > 0;
        const score = Number(s.payload.autoScore || 0) + (hasRefl ? Number(s.payload.reflectionScore) : 0);
        const max = Number(s.payload.autoMaxScore || 0) + (hasRefl ? Number(s.payload.reflectionMaxScore) : 0);
        if (max > 0) grade = calculateGradeByScale(score, max, activeScale);
      }
      if (!grade) grade = "IV";
      if (!student || !assignment) continue;
      if (!grouped[assignment]) grouped[assignment] = [];
      const className = (s.className || s.class || s.studentClass || "").trim();
      grouped[assignment].push({
        student,
        grade,
        className,
        date: s.topicDate || s.topicCreatedAt || s.assessmentDate || s.createdAt || s.completedAt || s.startedAt || null,
      });
    }
    return grouped;
  }

  async function loadInschoolAssessmentContext() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab || !tab.url || !tab.url.includes("inschool.visma.no")) {
      throw new Error("Åpne vurderingen i Visma InSchool i den aktive fanen først.");
    }
    const origin = new URL(tab.url).origin;
    const [{ result }] = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      func: async (baseOrigin) => {
        const urls = [window.location.href, ...performance.getEntriesByType("resource").map((r) => r.name)];
        let groupId = null;
        let assessmentId = null;
        let lightUrl = null;
        for (const u of urls) {
          const gm = u.match(/\/(?:gradebook|group)\/(\d+)/);
          if (gm && !groupId) groupId = gm[1];
          const am = u.match(/\/details\/(\d+)/) || u.match(/assessmentId=(\d+)/);
          if (am && !assessmentId) assessmentId = am[1];
        }
        if (groupId) {
          const matchingLights = urls.filter((u) => u.includes("includeGradeHistory=false") && u.includes(`/group/${groupId}/`));
          if (matchingLights.length > 0) lightUrl = matchingLights[matchingLights.length - 1];
        }
        if (!groupId) return { error: "Fant ikke klasse/gruppe-ID i InSchool. Prøv å laste inn InSchool-siden på nytt (Cmd+R / F5)." };

        const fetchUrl = lightUrl || `${baseOrigin}/control/assessment/group/${groupId}/light?includeGradeHistory=false&_=${Date.now()}`;
        const lightResp = await fetch(fetchUrl, {
          credentials: "include",
        });
        if (!lightResp.ok) return { error: `Kunne ikke hente elevliste (HTTP ${lightResp.status}). Prøv å laste inn InSchool-siden på nytt (Cmd+R / F5).` };
        const lightData = await lightResp.json();
        return { origin: baseOrigin, groupId, assessmentId, lightData };
      },
      args: [origin],
    });
    if (!result) throw new Error("Kunne ikke lese InSchool-fanen. Prøv å laste inn InSchool-siden på nytt (Cmd+R / F5).");
    if (result.error) throw new Error(result.error);
    return result;
  }

  function normalizeName(raw) {
    const cleaned = String(raw || "").normalize("NFC").trim().replace(/\s+/g, " ");
    if (cleaned.includes(",")) {
      const [last, ...rest] = cleaned.split(",");
      const first = rest.join(" ").trim();
      if (first && last) return `${first} ${last.trim()}`.toLowerCase();
    }
    return cleaned.toLowerCase();
  }

  function findLearnerArray(obj, depth = 0) {
    if (!obj || typeof obj !== "object" || depth > 4) return null;
    if (Array.isArray(obj)) {
      if (
        obj.length > 0 &&
        typeof obj[0] === "object" &&
        obj[0] !== null &&
        ("learnerId" in obj[0] || "studentId" in obj[0] || "fullName" in obj[0] || "learnerName" in obj[0] || "givenName" in obj[0] || "firstName" in obj[0])
      ) {
        return obj;
      }
      for (const item of obj) {
        const found = findLearnerArray(item, depth + 1);
        if (found) return found;
      }
      return null;
    }
    for (const val of Object.values(obj)) {
      const found = findLearnerArray(val, depth + 1);
      if (found) return found;
    }
    return null;
  }

  function buildLearnerMap(lightData) {
    const ld = (lightData && lightData.data) || lightData || {};
    const tg = ld.teachingGroup || {};
    let learners =
      ld.learners ||
      ld.students ||
      tg.learners ||
      tg.students ||
      tg.members ||
      tg.pupils ||
      findLearnerArray(ld) ||
      [];
    const map = new Map();
    for (const l of learners) {
      const raw =
        l.fullName ||
        l.name ||
        l.displayName ||
        l.learnerName ||
        l.studentName ||
        `${l.givenName || l.firstName || ""} ${l.familyName || l.lastName || ""}`;
      const norm = normalizeName(raw);
      if (norm) map.set(norm, l);
    }
    return map;
  }

  function findLearnerMatch(learnerByName, rawName) {
    const norm = normalizeName(rawName);
    if (!norm) return null;
    if (learnerByName.has(norm)) return learnerByName.get(norm);
    const parts = norm.split(" ").filter(Boolean);
    if (parts.length >= 2) {
      const fl = `${parts[0]} ${parts[parts.length - 1]}`;
      const flMatches = [];
      for (const [k, v] of learnerByName.entries()) {
        const kp = k.split(" ");
        if (kp.length >= 2 && `${kp[0]} ${kp[kp.length - 1]}` === fl) flMatches.push(v);
      }
      if (flMatches.length === 1) return flMatches[0];
    }
    if (parts.length >= 2) return null;
    const firstMatches = [];
    for (const [k, v] of learnerByName.entries()) {
      if (k.split(" ")[0] === parts[0]) firstMatches.push(v);
    }
    return firstMatches.length === 1 ? firstMatches[0] : null;
  }

  const duplicatesWrapEl = document.getElementById("grade-duplicates-wrap");
  let duplicateChoices = {};

  function matchesInschoolClass(item, ctx) {
    if (!item.className || !ctx || !ctx.lightData || !ctx.lightData.teachingGroup) return true;
    const tg = ctx.lightData.teachingGroup;
    const groupName = String(tg.name || tg.groupName || tg.code || "").toUpperCase();
    if (!groupName) return true;
    const itemClass = item.className.toUpperCase();
    if (groupName.includes(itemClass)) return true;
    return false;
  }

  function resolveDuplicates(assignmentName, entries) {
    const byStudent = new Map();
    for (const item of entries) {
      const norm = (item.student || "").trim().toLowerCase();
      if (!byStudent.has(norm)) byStudent.set(norm, []);
      byStudent.get(norm).push(item);
    }
    const resolvedItems = [];
    const conflicts = [];
    for (const [norm, list] of byStudent.entries()) {
      const uniqueGrades = [...new Set(list.map((i) => i.grade))];
      const key = `${assignmentName}:::${norm}`;
      if (uniqueGrades.length > 1) {
        const chosen = duplicateChoices[key] && uniqueGrades.includes(duplicateChoices[key])
          ? duplicateChoices[key]
          : list[list.length - 1].grade;
        duplicateChoices[key] = chosen;
        conflicts.push({ key, assignmentName, student: list[0].student, grades: uniqueGrades, chosen });
        resolvedItems.push({ ...list[0], grade: chosen });
      } else {
        resolvedItems.push(list[0]);
      }
    }
    return { resolvedItems, conflicts };
  }

  function renderDuplicatesBox(titles) {
    if (!duplicatesWrapEl) return;
    const allConflicts = [];
    for (const t of titles) {
      const { conflicts } = resolveDuplicates(t, parsedByAssignment[t] || []);
      allConflicts.push(...conflicts);
    }
    if (allConflicts.length === 0) {
      duplicatesWrapEl.style.display = "none";
      duplicatesWrapEl.innerHTML = "";
      return;
    }
    duplicatesWrapEl.innerHTML = `<div style="font-weight:700; margin-bottom:4px;">⚠ Duplikater funnet – velg hvilken karakter som skal beholdes:</div>`;
    for (const c of allConflicts) {
      const row = document.createElement("div");
      row.style.cssText = "display:flex; align-items:center; justify-content:space-between; gap:6px; margin-top:3px;";
      const lbl = document.createElement("span");
      lbl.textContent = titles.length > 1 ? `${c.student} (${c.assignmentName})` : c.student;
      lbl.style.cssText = "overflow:hidden; text-overflow:ellipsis; white-space:nowrap;";
      const sel = document.createElement("select");
      sel.style.cssText = "width:auto; padding:2px 4px; font-size:11px; margin:0; flex-shrink:0;";
      for (const g of c.grades) {
        const opt = document.createElement("option");
        opt.value = g;
        opt.textContent = g;
        sel.appendChild(opt);
      }
      sel.value = c.chosen;
      sel.addEventListener("change", () => {
        duplicateChoices[c.key] = sel.value;
        renderPreview();
      });
      row.appendChild(lbl);
      row.appendChild(sel);
      duplicatesWrapEl.appendChild(row);
    }
    duplicatesWrapEl.style.display = "block";
  }

  function renderPreview() {
    const checked = multiWrapEl
      ? Array.from(multiWrapEl.querySelectorAll(".grade-multi-cb:checked")).map((c) => c.value)
      : [];
    const learnerByName = buildLearnerMap(currentInschoolCtx && currentInschoolCtx.lightData);
    previewBodyEl.innerHTML = "";
    renderDuplicatesBox(checked.length > 0 ? checked : [assignmentSelectEl.value].filter(Boolean));

    if (checked.length > 1) {
      matchedRows = [];
      for (const title of checked) {
        const { resolvedItems: entries } = resolveDuplicates(title, parsedByAssignment[title] || []);
        let count = 0;
        for (const item of entries) {
          const m = findLearnerMatch(learnerByName, item.student);
          if (m) {
            count++;
            matchedRows.push({ learnerId: m.learnerId || m.id || m.studentId, student: item.student, grade: item.grade });
          }
        }
        const scale = assignmentScales[title] || (gradeScaleSelect && gradeScaleSelect.value) || "Findelt med IV";
        const tr = document.createElement("tr");
        tr.innerHTML = `<td>${title}</td><td><strong>${scale}</strong></td><td>✓ ${count} elever</td>`;
        tr.style.color = "#065f46";
        previewBodyEl.appendChild(tr);
      }
      previewWrapEl.style.display = "block";
      btnSaveGrades.disabled = matchedRows.length === 0;
      return;
    }

    const assignment = checked[0] || assignmentSelectEl.value;
    const { resolvedItems: entries } = resolveDuplicates(assignment, parsedByAssignment[assignment] || []);

    previewBodyEl.innerHTML = "";
    matchedRows = [];
    const unmatchedRows = [];
    for (const item of entries) {
      const match = findLearnerMatch(learnerByName, item.student);
      if (match) {
        matchedRows.push({ learnerId: match.learnerId || match.id || match.studentId, student: item.student, grade: item.grade });
      } else {
        unmatchedRows.push(item);
      }
    }
    const rowsToShow = matchedRows.length > 0 ? matchedRows : unmatchedRows;
    for (const r of rowsToShow) {
      const tr = document.createElement("tr");
      const isOk = matchedRows.length > 0;
      tr.innerHTML = `<td>${r.student}</td><td><strong>${r.grade}</strong></td><td>${isOk ? "✓ Klar" : "⚠ Ikke funnet"}</td>`;
      tr.style.color = isOk ? "#065f46" : "#991b1b";
      previewBodyEl.appendChild(tr);
    }
    previewWrapEl.style.display = "block";
    btnSaveGrades.disabled = matchedRows.length === 0;
  }

  btnPickGradeFile.addEventListener("click", () => {
    gradeFileEl.value = "";
    gradeFileEl.click();
  });

  gradeFileEl.addEventListener("change", async () => {
    const file = gradeFileEl.files[0];
    if (!file) return;
    setGradeStatus("Leser fil og henter elever fra InSchool...", "info");
    try {
      if (file.name.endsWith(".xlsx")) {
        lastJsonText = null;
        const rows = await parseXlsxRows(await file.arrayBuffer());
        parsedByAssignment = parseTeamsRows(rows);
      } else {
        const text = await file.text();
        lastJsonText = file.name.endsWith(".json") ? text : null;
        parsedByAssignment = lastJsonText ? parseCuriosaJson(text) : parseTeamsRows(parseCsv(text));
      }
      const names = Object.keys(parsedByAssignment);
      if (names.length === 0) throw new Error("Fant ingen oppgaver med karakterer i filen.");

      currentInschoolCtx = await loadInschoolAssessmentContext();
      const learnerByName = buildLearnerMap(currentInschoolCtx.lightData);
      const existingAssessments =
        (currentInschoolCtx && currentInschoolCtx.lightData && (currentInschoolCtx.lightData.assessmentList || currentInschoolCtx.lightData.assessments)) || [];
      const existsInInschool = (title) => {
        const target = title.trim().toLowerCase();
        if (!target) return false;
        return existingAssessments.some((a) => {
          const existingTitle = (a.title || a.name || "").trim().toLowerCase();
          return existingTitle && (existingTitle === target || existingTitle.includes(target) || target.includes(existingTitle));
        });
      };
      const scored = names.map((n) => ({
        name: n,
        matchCount: parsedByAssignment[n].filter((item) => Boolean(findLearnerMatch(learnerByName, item.student))).length,
      }));
      const strongMatches = scored.filter((s) => s.matchCount >= 3);
      const matching = strongMatches.length > 0 ? strongMatches : scored.filter((s) => s.matchCount > 0);
      const toShow = matching.length > 0 ? matching : scored;

      assignmentSelectEl.innerHTML = "";
      if (multiWrapEl) multiWrapEl.innerHTML = "";
      toShow.forEach((s, idx) => {
        const opt = document.createElement("option");
        opt.value = s.name;
        opt.textContent =
          matching.length > 0
            ? `${s.name} (${s.matchCount} i denne klassen)`
            : `${s.name} (${parsedByAssignment[s.name].length})`;
        assignmentSelectEl.appendChild(opt);

        if (multiWrapEl) {
          const row = document.createElement("div");
          row.style.cssText = "display:flex; align-items:center; justify-content:space-between; gap:4px; margin:3px 0;";
          const lbl = document.createElement("label");
          lbl.style.cssText = "display:flex; align-items:center; gap:5px; margin:0; font-weight:500; cursor:pointer; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;";
          const cb = document.createElement("input");
          cb.type = "checkbox";
          cb.className = "grade-multi-cb";
          cb.value = s.name;
          cb.checked = !existsInInschool(s.name);
          cb.style.cssText = "width:auto; margin:0; flex-shrink:0;";
          cb.addEventListener("change", () => {
            assignmentSelectEl.value = s.name;
            if (gradeScaleSelect && assignmentScales[s.name]) gradeScaleSelect.value = assignmentScales[s.name];
            renderPreview();
          });
          lbl.appendChild(cb);
          lbl.appendChild(document.createTextNode(`${s.name} (${matching.length > 0 ? s.matchCount : parsedByAssignment[s.name].length})`));

          const sel = document.createElement("select");
          sel.style.cssText = "width:auto; padding:2px 4px; font-size:10px; margin:0; flex-shrink:0;";
          for (const [val, txt] of [["Findelt med IV", "1–6"], ["Måloppnåelse", "Lav/Mid/Høy"], ["Godkjent/Ikke godkjent", "GK/IGK"]]) {
            const o = document.createElement("option");
            o.value = val;
            o.textContent = txt;
            sel.appendChild(o);
          }
          sel.value = assignmentScales[s.name] || (gradeScaleSelect && gradeScaleSelect.value) || "Findelt med IV";
          sel.addEventListener("change", () => {
            assignmentScales[s.name] = sel.value;
            if (assignmentSelectEl.value === s.name && gradeScaleSelect) {
              gradeScaleSelect.value = sel.value;
            }
            if (lastJsonText) {
              parsedByAssignment = parseCuriosaJson(lastJsonText);
              renderPreview();
            }
          });

          row.appendChild(lbl);
          row.appendChild(sel);
          multiWrapEl.appendChild(row);
        }
      });
      assignmentWrapEl.style.display = "block";
      createNewCheckbox.checked = !currentInschoolCtx.assessmentId;
      if (gradeScaleSelect && assignmentScales[assignmentSelectEl.value]) {
        gradeScaleSelect.value = assignmentScales[assignmentSelectEl.value];
      }
      renderPreview();
      if (matching.length === 0) {
        const keys = Object.keys((currentInschoolCtx && currentInschoolCtx.lightData) || {}).join(", ");
        setGradeStatus(`Fant 0 matchende elever i InSchool (keys: ${keys || "tom"}).`, "err");
      } else {
        setGradeStatus(
          `Klar! (Gruppe ${currentInschoolCtx.groupId}) Fant ${toShow.length} oppgave(r) for denne klassen. Sjekk forhåndsvisningen før du lagrer.`,
          "ok"
        );
      }
    } catch (err) {
      setGradeStatus(`Feil: ${err.message}`, "err");
    }
  });

  assignmentSelectEl.addEventListener("change", () => {
    if (gradeScaleSelect && assignmentScales[assignmentSelectEl.value]) {
      gradeScaleSelect.value = assignmentScales[assignmentSelectEl.value];
    }
    renderPreview();
  });

  if (gradeScaleSelect) {
    gradeScaleSelect.addEventListener("change", () => {
      if (lastJsonText) {
        parsedByAssignment = parseCuriosaJson(lastJsonText);
        renderPreview();
      }
    });
  }

  btnSaveGrades.addEventListener("click", async () => {
    if (!currentInschoolCtx || matchedRows.length === 0) return;
    const { origin, groupId, assessmentId, lightData } = currentInschoolCtx;
    const createNew = createNewCheckbox && createNewCheckbox.checked;
    const checkedBoxes = multiWrapEl ? Array.from(multiWrapEl.querySelectorAll(".grade-multi-cb:checked")).map((c) => c.value) : [];
    const selectedTitles = checkedBoxes.length > 0 ? checkedBoxes : [assignmentSelectEl.value];
    if (!assessmentId && !createNew) {
      setGradeStatus("Åpne en vurdering i InSchool, eller huk av for å opprette ny.", "err");
      return;
    }

    btnSaveGrades.disabled = true;
    setGradeStatus(
      createNew ? `Oppretter og lagrer ${selectedTitles.length} oppgave(r)...` : "Lagrer karakterer i InSchool...",
      "info"
    );
    try {
      const xsrfCookie = await chrome.cookies.get({ url: origin, name: "XSRF-TOKEN" });
      const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.value) : "";
      const tenant = lightData.tenant || 8004;
      const assessments = lightData.assessmentList || lightData.assessments || [];
      const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
      const learnerByName = buildLearnerMap(lightData);

      let totalOk = 0;
      let totalRows = 0;
      let lastErr = "";

      for (const title of selectedTitles) {
        const { resolvedItems: entries } = resolveDuplicates(title, parsedByAssignment[title] || []);
        const rowsForTitle = [];
        const seenLearnerIds = new Set();
        const rowsByLearnerId = new Map();
        for (const item of entries) {
          if (!matchesInschoolClass(item, currentInschoolCtx)) continue;
          const match = findLearnerMatch(learnerByName, item.student);
          if (match) {
            const lid = String(match.learnerId || match.id || match.studentId);
            const wordCount = normalizeName(item.student).split(" ").filter(Boolean).length;
            const prev = rowsByLearnerId.get(lid);
            if (!prev || wordCount > prev.wordCount) {
              rowsByLearnerId.set(lid, { learnerId: lid, student: item.student, grade: item.grade, wordCount });
            }
          }
        }
        for (const row of rowsByLearnerId.values()) {
          seenLearnerIds.add(row.learnerId);
          rowsForTitle.push({ learnerId: row.learnerId, student: row.student, grade: row.grade });
        }
        for (const l of learnerByName.values()) {
          const lid = l.learnerId || l.id || l.studentId;
          if (lid && !seenLearnerIds.has(String(lid))) {
            seenLearnerIds.add(String(lid));
            rowsForTitle.push({ learnerId: lid, student: l.fullName || l.name || "", grade: "IV" });
          }
        }
        if (rowsForTitle.length === 0) continue;
        totalRows += rowsForTitle.length;

        const titleScale = assignmentScales[title] || (gradeScaleSelect && gradeScaleSelect.value) || "Findelt med IV";
        let targetAssessmentId = assessmentId;
        if (createNew) {
          const existing = assessments.find(
            (a) => String(a.name || "").trim().toLowerCase() === title.trim().toLowerCase()
          );
          if (existing && (existing.id || existing.assessmentId)) {
            targetAssessmentId = existing.id || existing.assessmentId;
          } else {
            const allLearners = Array.from(learnerByName.values())
              .map((l) => l.learnerId || l.id || l.studentId)
              .filter(Boolean);
            const firstWithDate = entries.find((e) => e.date);
            const d = firstWithDate ? new Date(firstWithDate.date) : new Date();
            const now = Number.isNaN(d.getTime()) ? new Date() : d;
            const dd = String(now.getDate()).padStart(2, "0");
            const mm = String(now.getMonth() + 1).padStart(2, "0");
            const yyyy = now.getFullYear();
            const [{ result: createRes }] = await chrome.scripting.executeScript({
              target: { tabId: tab.id },
              func: async (baseOrigin, gid, name, dateStr, learnerIds, token, urlGid, scaleType) => {
                try {
                  const resp = await fetch(`${baseOrigin}/control/assessment?_=${Date.now()}`, {
                    method: "POST",
                    credentials: "include",
                    headers: {
                      "Content-Type": "application/json",
                      ...(token ? { "X-Xsrf-Token": token } : {}),
                    },
                    body: JSON.stringify({
                      assessmentDate: dateStr,
                      competenceIds: [],
                      description: "",
                      evalType: 0,
                      gradeSetType: scaleType,
                      groupId: Number(gid),
                      learners: learnerIds.map((id) => ({ id })),
                      lessons: [],
                      name,
                      type: "FORMATIVE",
                    }),
                  });
                  if (!resp.ok) {
                    const errTxt = await resp.text().catch(() => "");
                    return { error: `Kunne ikke opprette vurdering (HTTP ${resp.status}, gid=${gid}, n=${learnerIds.length}${errTxt ? `: ${errTxt.slice(0, 80)}` : ""}).` };
                  }
                  const txt = await resp.text().catch(() => "");
                  let data = {};
                  try { data = txt ? JSON.parse(txt) : {}; } catch (_) {}
                  let newId = data.id || data.assessmentId || (data.data && data.data.id);
                  if (!newId) {
                    await new Promise((r) => setTimeout(r, 350));
                    const perfLights = performance.getEntriesByType("resource").filter((r) => r.name.includes("includeGradeHistory=false") && r.name.includes(`/group/${urlGid}/`));
                    const perfLight = perfLights[perfLights.length - 1];
                    const lUrl = perfLight
                      ? perfLight.name.replace(/([?&])_=\d+/, `$1_=${Date.now()}`)
                      : `${baseOrigin}/control/assessment/group/${urlGid}/light?includeGradeHistory=false&_=${Date.now()}`;
                    const lResp = await fetch(lUrl, { credentials: "include" });
                    if (lResp.ok) {
                      const lData = await lResp.json();
                      const list = lData.assessmentList || lData.assessments || [];
                      const created = list.find((a) => String(a.name || "").trim().toLowerCase() === name.toLowerCase());
                      if (created) newId = created.id || created.assessmentId;
                    }
                  }
                  return { id: newId || null };
                } catch (e) {
                  return { error: e.message || String(e) };
                }
              },
              args: [
                origin,
                (lightData.teachingGroup && (lightData.teachingGroup.hybridId || lightData.teachingGroup.id)) || groupId,
                title.trim(),
                `${dd}/${mm}/${yyyy}`,
                allLearners,
                xsrfToken,
                groupId,
                titleScale,
              ],
            });
            if (!createRes || createRes.error || !createRes.id) {
              const tgKeys = Object.keys(lightData.teachingGroup || {}).join(",");
              throw new Error(`${(createRes && createRes.error) || "Fikk ikke vurderings-ID"} (tgKeys: ${tgKeys})`);
            }
            targetAssessmentId = createRes.id;
          }
        }

        const currentAss = assessments.find((a) => String(a.id) === String(targetAssessmentId)) || {};
        const gradeSetType = currentAss.gradeSetType || titleScale;
        const [{ result: savedCount }] = await chrome.scripting.executeScript({
          target: { tabId: tab.id },
          func: async (baseOrigin, gId, aId, gType, tId, rows, xsrf) => {
            try {
              let ok = 0;
              let err = "";
            for (const r of rows) {
              const isNoIvScale = /måloppnåelse|godkjent/i.test(gType);
              const mappedGrade =
                r.grade === "IV" && isNoIvScale
                  ? "ABS"
                  : r.grade === "Godkjent"
                    ? "GK"
                    : r.grade === "Ikke godkjent"
                      ? "IGK"
                      : r.grade;
              let resp = await fetch(`${baseOrigin}/control/assessment/group/${gId}/result?_=${Date.now()}`, {
                method: "POST",
                credentials: "include",
                headers: { "Content-Type": "application/json", "X-Xsrf-Token": xsrf },
                body: JSON.stringify({
                  assessmentId: Number(aId),
                  assessmentType: "FORMATIVE",
                  grade: mappedGrade,
                  gradeChange: null,
                  gradeSetType: gType,
                  learnerId: Number(r.learnerId),
                  tenant: Number(tId),
                }),
              });
              if (resp.status === 400 && (mappedGrade === "GK" || mappedGrade === "IGK")) {
                const altGrade = mappedGrade === "GK" ? "Godkjent" : "Ikke godkjent";
                resp = await fetch(`${baseOrigin}/control/assessment/group/${gId}/result?_=${Date.now()}`, {
                  method: "POST",
                  credentials: "include",
                  headers: { "Content-Type": "application/json", "X-Xsrf-Token": xsrf },
                  body: JSON.stringify({
                    assessmentId: Number(aId),
                    assessmentType: "FORMATIVE",
                    grade: altGrade,
                    gradeChange: null,
                    gradeSetType: gType === "Godkjent/ikke godkjent" ? "Godkjent/Ikke godkjent" : gType,
                    learnerId: Number(r.learnerId),
                    tenant: Number(tId),
                  }),
                });
              }
              if (resp.ok) {
                ok++;
              } else if (!err) {
                const txt = await resp.text().catch(() => "");
                err = `HTTP ${resp.status} [g=${r.grade}, t=${gType}]${txt ? `: ${txt.slice(0, 80)}` : ""}`;
              }
            }
              return { ok, err };
            } catch (e) {
              return { ok: 0, err: e.message || String(e) };
            }
          },
          args: [origin, groupId, targetAssessmentId, gradeSetType, tenant, rowsForTitle, xsrfToken],
        });
        totalOk += (savedCount && savedCount.ok) || 0;
        if (!lastErr && savedCount && savedCount.err) lastErr = savedCount.err;
      }

      if (totalOk === 0 && lastErr) {
        setGradeStatus(`Lagret 0 av ${totalRows} (${lastErr})`, "err");
      } else {
        setGradeStatus(
          `Lagret ${totalOk} av ${totalRows} karakterer (${selectedTitles.length} oppgave(r))! Siden lastes inn på nytt om 5 sek...`,
          "ok"
        );
        if (btnRefreshInschool) btnRefreshInschool.style.display = "block";
        if (autoRefreshTimer) clearTimeout(autoRefreshTimer);
        const targetTabId = tab.id;
        autoRefreshTimer = setTimeout(() => {
          autoRefreshTimer = null;
          chrome.tabs.reload(targetTabId);
        }, 5000);
      }
    } catch (err) {
      setGradeStatus(`Feil: ${err.message}`, "err");
    } finally {
      btnSaveGrades.disabled = false;
    }
  });

  let autoRefreshTimer = null;
  if (btnRefreshInschool) {
    btnRefreshInschool.addEventListener("click", async () => {
      if (autoRefreshTimer) {
        clearTimeout(autoRefreshTimer);
        autoRefreshTimer = null;
      }
      const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
      if (tab && tab.id) chrome.tabs.reload(tab.id);
    });
  }
})();
