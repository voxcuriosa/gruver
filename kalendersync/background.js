chrome.runtime.onMessage.addListener((msg) => {
  if (!msg || msg.type !== "autoCalendarSyncDone") return;
  const toOutlook = msg.toOutlook ?? 0;
  const toInschool = msg.toInschool ?? 0;

  chrome.action.setBadgeText({ text: "✓" });
  chrome.action.setBadgeBackgroundColor({ color: "#059669" });

  chrome.notifications.create({
    type: "basic",
    iconUrl: "icon128.png",
    title: "InSchool Kalender Sync",
    message: `Automatisk synk ferdig: ${toOutlook} til Outlook, ${toInschool} til InSchool.`,
  });
});
