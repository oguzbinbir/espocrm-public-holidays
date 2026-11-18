import MultiEnumFieldView from 'views/fields/multi-enum';

/**
 * Multi-select of holidays to exclude. The options depend on the selected region
 * and are loaded from the backend.
 */
export default class ExcludedHolidaysFieldView extends MultiEnumFieldView {

    regionField = 'phHolidayRegion'

    setup() {
        this.params.options = [];

        super.setup();

        this.holidayNames = {};

        this.wait(this.loadOptions(true));

        this.listenTo(this.model, `change:${this.regionField}`, async () => {
            await this.loadOptions(false);

            if (this.isRendered()) {
                await this.reRender();
            }
        });
    }

    setupTranslation() {
        this.translatedOptions = {};
    }

    async loadOptions(silent) {
        const region = this.model.get(this.regionField);

        let list = [];

        if (region) {
            try {
                list = await Espo.Ajax.getRequest('PublicHolidays/regionHolidays', {region: region});
            } catch (e) {
                list = [];
            }
        }

        list.forEach(item => this.holidayNames[item.key] = item.name);

        this.translatedOptions = {...this.holidayNames};

        this.setOptionList(list.map(item => item.key), silent);
    }
}
