# Changelog

All notable changes to this extension are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.1.2] - 2026-11-20

### Added
- Published under the MIT License.

### Changed
- The description of automatically created exceptions is translated (system language) and contains the readable region name, e.g. "Automatisch angelegt durch die Erweiterung „Feiertage“ (Deutschland – Baden-Württemberg)." Existing exceptions keep their description.
- Minimum EspoCRM version is now 10.0.4 (the oldest tested version).
- README in English and German, with a flow diagram.

## [0.1.1] - 2026-11-19

### Changed
- Clearer tooltip for the lead time setting (0 to 5 years).

## [0.1.0] - 2026-11-18

### Added
- Public holiday region per working time calendar (156 countries and regions via Yasumi) and excluded holidays.
- Automatic non-working exceptions for statutory public holidays, shared per region and linked to all matching calendars.
- Synchronization via a weekly scheduled job, when saving calendars and users, and manually from the calendar menu.
- Settings: lead time in years, only calendars of active users.
- English and German translations.
