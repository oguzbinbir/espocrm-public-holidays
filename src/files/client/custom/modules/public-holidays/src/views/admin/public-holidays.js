import SettingsEditRecordView from 'views/settings/record/edit';

/**
 * Administration > Public Holidays.
 */
export default class PublicHolidaysSettingsView extends SettingsEditRecordView {

    layoutName = 'publicHolidays'
    saveAndContinueEditingAction = false
}
