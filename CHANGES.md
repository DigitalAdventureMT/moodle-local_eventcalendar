# Changelog

## 2.0.0

First public release.

- Calendars from templates (Advent, Lent, New Year, Halloween, Chinese New Year) or custom; fixed, calculated or lookup-table dates.
- Grid of days with progressive unlocking and visited/not visited states.
- Day management: content written freely or chosen from the content pack, notification title and body from the notification set, internal notes, progress status, preview and test sending of emails and notifications.
- Day previews shown in a sandbox that runs no script and cannot be embedded in other sites.
- Images in the day content: the editor accepts only images, and any other file in the plugin areas is downloaded instead of being opened in the browser.
- The files of a day follow the same rules as the day page: visible calendar, access rules satisfied and, for the content, day unlocked. Content managers can always see them.
- Configurable weekly rest day.
- Site logo, preloaded banner and preloaded header/footer pair for each calendar.
- Notification subscriptions and daily sending in each user's time zone, with a configurable maximum number of emails per run and a single retry of failed sendings.
- Notification emails whose images load in external mail clients, through a personal link of the recipient.
- Unsubscribe link without login in every notification email, with List-Unsubscribe headers.
- Email template chosen for each calendar.
- Notification sets created from the standard packs and updated when a new version of the pack is available.
- Detection of new icon sets, email templates, banners and header/footer pairs added to the plugin folders.
- Access rules per calendar (system role, system cohort), up to 50 blocks of 20 conditions, and alerts for rules that are no longer valid.
- Automatic hiding of expired calendars.
- Day opened event.
- Calendar reports page with totals per calendar.
- Ready-made code to publish each calendar: link, card with banner, button.
- Subplugin types `ecaddon` and `ecpack`, and hooks to extend forms, lists, access rules, publishing options and reports.
- Privacy API.
- English language strings; Italian translation in `lang/it`.
- Accessibility review (WCAG 2.1 AA) and Behat tests for accessibility.
- Supported Moodle versions: 4.5 to 5.3.
