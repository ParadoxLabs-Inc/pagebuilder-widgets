/*eslint-disable */

/* jscs:disable */

function _inheritsLoose(subClass, superClass) {
    subClass.prototype = Object.create(superClass.prototype);
    subClass.prototype.constructor = subClass;
    subClass.__proto__ = superClass;
}

define(
    [
        "Magento_PageBuilder/js/mass-converter/widget-directive-abstract",
        "Magento_PageBuilder/js/utils/object"
    ],
    function (_widgetDirectiveAbstract, _object) {
        /**
         * @api
         */
        var WidgetDirective =
            /*#__PURE__*/
            function (_widgetDirectiveAbstr) {
                "use strict";

                _inheritsLoose(WidgetDirective, _widgetDirectiveAbstr);

                function WidgetDirective() {
                    return _widgetDirectiveAbstr.apply(this, arguments) || this;
                }

                var _proto = WidgetDirective.prototype;

                /**
                 * Convert value to internal format
                 *
                 * @param {object} data
                 * @param {object} config
                 * @returns {object}
                 */
                _proto.fromDom = function fromDom(data, config) {
                    var attributes = _widgetDirectiveAbstr.prototype.fromDom.call(this, data, config);

                    data.category_ids = attributes.category_ids.split(',');
                    data.categories_count = attributes.categories_count;
                    data.sort_order = attributes.sort_order;
                    data.show_name = attributes.show_name;

                    return data;
                }
                /**
                 * Convert value to knockout format
                 *
                 * @param {object} data
                 * @param {object} config
                 * @returns {object}
                 */
                ;

                _proto.toDom = function toDom(data, config) {
                    var attributes = {
                        type: "ParadoxLabs\\PageBuilderWidgets\\Block\\Category\\CategoriesList",
                        template: "ParadoxLabs_PageBuilderWidgets::catalog/category/widget/content/carousel.phtml",
                        anchor_text: "",
                        id_path: "",
                        show_pager: 0,
                        categories_count: data.categories_count,
                        category_ids: data.category_ids,
                        show_name: data.show_name,
                        type_name: "Catalog Categories Carousel"
                    };

                    if (data.sort_order) {
                        attributes.sort_order = data.sort_order;
                    }

                    (0, _object.set)(data, config.html_variable, this.buildDirective(attributes));
                    return data;
                }
                /**
                 * @param {string} content
                 * @returns {string}
                 */
                ;

                return WidgetDirective;
            }(_widgetDirectiveAbstract);

        return WidgetDirective;
    }
);
