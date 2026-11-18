import ActionHandler from 'action-handler';

/**
 * "Sync Public Holidays" action in the detail view of a working time calendar.
 */
export default class SyncPublicHolidaysActionHandler extends ActionHandler {

    async process() {
        const model = this.view.model;

        Espo.Ui.notifyWait();

        let result;

        try {
            result = await Espo.Ajax.postRequest(`PublicHolidays/calendar/${model.id}/sync`);
        } catch (e) {
            return;
        }

        const language = this.view.getLanguage();

        if (result.calendarsSkipped > 0) {
            Espo.Ui.warning(language.translate('syncSkipped', 'messages', 'WorkingTimeCalendar'));

            return;
        }

        const message = language.translate('syncDone', 'messages', 'WorkingTimeCalendar')
            .replace('{linked}', result.linked)
            .replace('{unlinked}', result.unlinked)
            .replace('{created}', result.rangesCreated);

        Espo.Ui.success(message);

        // Refresh the exceptions panel.
        model.trigger('update-related:ranges');
    }
}
