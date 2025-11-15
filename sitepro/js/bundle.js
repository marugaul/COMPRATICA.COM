var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
define("js/Serializable", ["require", "exports"], function (require, exports) {
    "use strict";
    exports.__esModule = true;
    exports.Serializable = void 0;
    var Serializable = (function () {
        function Serializable() {
        }
        Serializable.copy = function (value, target, skipSerializable, filter) {
            if (target === void 0) { target = null; }
            if (skipSerializable === void 0) { skipSerializable = false; }
            if (filter === void 0) { filter = null; }
            if (!skipSerializable && value instanceof Serializable) {
                var res = value.serialize();
                if (target) {
                    res = Serializable.copy(res, target);
                }
                return res;
            }
            else if (value instanceof Array) {
                var arr = [];
                for (var i = 0; i < value.length; i++) {
                    if (!filter || filter.call(value, i, value[i])) {
                        arr.push(Serializable.copy(value[i]));
                    }
                }
                return arr;
            }
            else if (value instanceof Object) {
                var obj = target ? target : {};
                for (var k in value) {
                    if (value.hasOwnProperty(k) && typeof value[k] !== 'function' && (!filter || filter.call(value, k, value[k]))) {
                        obj[k] = Serializable.copy(value[k]);
                    }
                }
                return obj;
            }
            else if (typeof value === 'function') {
                return null;
            }
            return value;
        };
        Serializable.deserializeArray = function (valueType, data, target) {
            if (data === void 0) { data = null; }
            if (target === void 0) { target = null; }
            var arr = target ? target : [];
            if (data instanceof Array) {
                for (var i = 0; i < data.length; i++) {
                    var item = new valueType();
                    item.deserialize(data[i]);
                    arr.push(item);
                }
            }
            return arr;
        };
        Serializable.serializeValue = function (data, target, filter, skipSerializable) {
            if (target === void 0) { target = null; }
            if (filter === void 0) { filter = null; }
            if (skipSerializable === void 0) { skipSerializable = false; }
            return Serializable.copy(data, target, skipSerializable, filter);
        };
        Serializable.prototype.deserialize = function (data) {
            if (data === void 0) { data = null; }
            if (data && typeof data === 'object')
                Serializable.copy(data, this);
        };
        Serializable.prototype.serialize = function () {
            return Serializable.copy(this, null, true);
        };
        return Serializable;
    }());
    exports.Serializable = Serializable;
});
define("js/store/StoreShippingMethod", ["require", "exports", "js/Serializable"], function (require, exports, Serializable_1) {
    "use strict";
    exports.__esModule = true;
    exports.StoreShippingMethod = exports.StoreShippingMethodRange = exports.buildTypeList = exports.StoreShippingMethodType = void 0;
    var StoreShippingMethodType;
    (function (StoreShippingMethodType) {
        StoreShippingMethodType[StoreShippingMethodType["FREE"] = 0] = "FREE";
        StoreShippingMethodType[StoreShippingMethodType["FLAT_RATE"] = 1] = "FLAT_RATE";
        StoreShippingMethodType[StoreShippingMethodType["BY_WEIGHT"] = 2] = "BY_WEIGHT";
        StoreShippingMethodType[StoreShippingMethodType["BY_SUBTOTAL"] = 3] = "BY_SUBTOTAL";
    })(StoreShippingMethodType = exports.StoreShippingMethodType || (exports.StoreShippingMethodType = {}));
    function buildTypeList() {
        return [
            { id: StoreShippingMethodType.FREE, name: __('Free') },
            { id: StoreShippingMethodType.FLAT_RATE, name: __('Flat rate') },
            { id: StoreShippingMethodType.BY_WEIGHT, name: __('By weight') },
            { id: StoreShippingMethodType.BY_SUBTOTAL, name: __('By subtotal') },
        ];
    }
    exports.buildTypeList = buildTypeList;
    var StoreShippingMethodRange = (function () {
        function StoreShippingMethodRange(from, to, value) {
            if (from === void 0) { from = 0; }
            if (to === void 0) { to = 0; }
            if (value === void 0) { value = 0; }
            this.from = from;
            this.to = to;
            this.value = value;
            if (isNaN(this.from))
                this.from = 0;
            if (isNaN(this.to))
                this.to = 0;
            if (isNaN(this.value))
                this.value = 0;
        }
        return StoreShippingMethodRange;
    }());
    exports.StoreShippingMethodRange = StoreShippingMethodRange;
    var StoreShippingMethod = (function (_super) {
        __extends(StoreShippingMethod, _super);
        function StoreShippingMethod(data) {
            if (data === void 0) { data = null; }
            var _this = _super.call(this) || this;
            _this.id = 0;
            _this.name = "";
            _this.destinationZoneId = 0;
            _this.type = StoreShippingMethodType.FREE;
            _this.ranges = [];
            _this.estimatedShippingTime = { from: 0, to: 0 };
            _this.deserialize(data);
            return _this;
        }
        return StoreShippingMethod;
    }(Serializable_1.Serializable));
    exports.StoreShippingMethod = StoreShippingMethod;
});
define("store/js/StoreCartElement", ["require", "exports"], function (require, exports) {
    "use strict";
    exports.__esModule = true;
    exports.addToCart = exports.updateCartCounter = exports.init = exports.cartElenents = void 0;
    exports.cartElenents = [];
    function init(storeCartElementId, cartUrl) {
        var thisCartUrl = cartUrl, cart = $('#' + storeCartElementId).eq(0);
        if (!/\#/.test(thisCartUrl)) {
            var storeAnchor = findNearestStoreAnchor();
            if (storeAnchor)
                thisCartUrl += ('#' + storeAnchor);
        }
        cart.on('click', function () { location.href = thisCartUrl; });
        exports.cartElenents.push({
            elem: cart,
            updateView: function (value, noAnim) {
                if (!noAnim) {
                    cart.addClass('cartanim');
                    setTimeout(function () { cart.removeClass('cartanim'); }, 1000);
                }
                cart.find('.store-cart-counter').text('(' + value + ')');
            }
        });
    }
    exports.init = init;
    function updateCartCounter(total, needAnim) {
        if (needAnim === void 0) { needAnim = false; }
        for (var i = 0; i < exports.cartElenents.length; i++) {
            exports.cartElenents[i].updateView(total, !needAnim);
        }
    }
    exports.updateCartCounter = updateCartCounter;
    function addToCart(cartUrl, itemId, quantity, successCallback, doneCallback) {
        if (quantity === void 0) { quantity = 1; }
        var _successCallback = successCallback;
        var _doneCallback = doneCallback;
        $.get(cartUrl + 'add/' + itemId, { quantity: quantity }, function (data) {
            updateCartCounter(data.total, true);
            if (typeof _successCallback === 'function')
                _successCallback(data);
            if (typeof _doneCallback === 'function')
                _doneCallback(data);
        }).fail(function () {
            console.log('Error adding to cart');
            if (typeof _doneCallback === 'function')
                _doneCallback();
        });
    }
    exports.addToCart = addToCart;
    function findNearestStoreAnchor() {
        var stores = $('.wb-store');
        if (stores.length) {
            return stores.eq(0).find('.wb_anchor').attr('name');
        }
        return null;
    }
});
define("js/store/StoreTypes", ["require", "exports"], function (require, exports) {
    "use strict";
    exports.__esModule = true;
    exports.StorePriceOptions = exports.StoreCurrency = void 0;
    var StoreCurrency = (function () {
        function StoreCurrency(code, prefix, postfix) {
            this.code = code;
            this.prefix = prefix;
            this.postfix = postfix;
        }
        return StoreCurrency;
    }());
    exports.StoreCurrency = StoreCurrency;
    var StorePriceOptions = (function () {
        function StorePriceOptions(decimalPoint, decimalPlaces, thousandsSeparator) {
            if (decimalPoint === void 0) { decimalPoint = '.'; }
            if (decimalPlaces === void 0) { decimalPlaces = 2; }
            if (thousandsSeparator === void 0) { thousandsSeparator = ''; }
            this.decimalPoint = decimalPoint;
            this.decimalPlaces = decimalPlaces;
            this.thousandsSeparator = thousandsSeparator;
        }
        return StorePriceOptions;
    }());
    exports.StorePriceOptions = StorePriceOptions;
});
define("store/js/StoreModule", ["require", "exports"], function (require, exports) {
    "use strict";
    exports.__esModule = true;
    exports.StoreCartData = exports.StoreCartItem = void 0;
    var StoreCartItem = (function () {
        function StoreCartItem(name, sku, priceStr, price, qty) {
            this.name = name;
            this.sku = sku;
            this.priceStr = priceStr;
            this.price = price;
            this.qty = qty;
        }
        return StoreCartItem;
    }());
    exports.StoreCartItem = StoreCartItem;
    var StoreCartData = (function () {
        function StoreCartData() {
            this.items = [];
            this.subTotalPrice = '';
            this.taxPrice = '';
            this.shippingPrice = '';
            this.totalPrice = '';
        }
        return StoreCartData;
    }());
    exports.StoreCartData = StoreCartData;
});
define("store/js/StorePaymentFormsDirective", ["require", "exports", "store/js/StoreModule"], function (require, exports, StoreModule_1) {
    "use strict";
    exports.__esModule = true;
    exports.register = void 0;
    function setPayBtnOverlayVisible(container, visible) {
        if (visible) {
            container.children('div').css('opacity', 0.5);
            var overlay = $('<div class="wb-store-pay-btn-overlay">').append('<div class="ico-spin icon-wb-spinner">');
            container.append(overlay);
        }
        else {
            container.find('.wb-store-pay-btn-overlay').remove();
            container.children('div').css('opacity', '');
        }
    }
    function initForm(scope, elem, checkoutUrl, $http) {
        var form = elem.find('form[data-gateway-id]');
        form.on('submit', function (e, justSubmit) {
            if (justSubmit)
                return true;
            var canSubmit = scope.onSubmit();
            if (typeof canSubmit !== 'undefined' && !canSubmit) {
                scope.$apply();
                return false;
            }
            if (!checkoutUrl) {
                console.error('Not enough data');
                return false;
            }
            var gatewayId = form.attr('data-gateway-id'), formData = [], formElements = form[0].elements;
            for (var i = 0; i < formElements.length; i++) {
                var formField = formElements[i], fd = void 0, mlp = void 0;
                if (formField.value === '{price}') {
                    angular.element(formField).data('isPrice', true);
                }
                formData.push({
                    name: formField.name,
                    value: formField.value,
                    isPrice: !!angular.element(formField).data('isPrice'),
                    fixedDecimal: ((!isNaN(fd = parseInt(angular.element(formField).data('fixedDecimal'), 10))) ? fd : -1),
                    multiplier: ((!isNaN(mlp = parseInt(angular.element(formField).data('multiplier'), 10))) ? mlp : 0)
                });
            }
            setPayBtnOverlayVisible(elem, true);
            $http.post(checkoutUrl.replace('__GATEWAY_ID__', gatewayId), angular.toJson({ formData: formData }))
                .then(function (res) {
                var e = new jQuery.Event("submitcomplete");
                try {
                    form.trigger(e, [res.data]);
                }
                catch (ex) {
                    console.error(ex);
                }
                if (('error' in res.data) && res.data.error) {
                    alert(res.data.error);
                    setPayBtnOverlayVisible(elem, false);
                    return;
                }
                if (('instantRedirectUrl' in res.data) && res.data.instantRedirectUrl) {
                    location.href = res.data.instantRedirectUrl;
                    return;
                }
                if (('redirectUrl' in res.data) && res.data.redirectUrl) {
                    form.attr('action', res.data.redirectUrl);
                }
                if (('updateFields' in res.data) && res.data.updateFields) {
                    for (var field in res.data.updateFields) {
                        form.find('[name="' + field + '"]').val(res.data.updateFields[field]);
                    }
                }
                if (('deleteFields' in res.data) && res.data.deleteFields) {
                    for (var i in res.data.deleteFields) {
                        form.find('[name="' + res.data.deleteFields[i] + '"]').remove();
                    }
                }
                if (('createFields' in res.data) && res.data.createFields) {
                    for (var i in res.data.createFields) {
                        var input = $(res.data.createFields[i]);
                        var name_1 = input.attr('name');
                        form.find('[name="' + name_1 + '"]').remove();
                        form.append(res.data.createFields[i]);
                    }
                }
                e = new jQuery.Event("beforesend");
                try {
                    form.trigger(e, [res.data]);
                }
                catch (ex) {
                    console.error(ex);
                }
                if (!e.isDefaultPrevented() && (!('noSubmit' in res.data) || !res.data.noSubmit)) {
                    form.trigger('submit', [true]);
                }
                else {
                    setPayBtnOverlayVisible(elem, false);
                }
            })["catch"](function () { console.log('Error processing checkout'); });
            return false;
        });
    }
    function initInqueryFrom(scope, elem, storeData) {
        var form = elem.find('form.wb_form').eq(0);
        var thisCartItems = (storeData ? storeData.items : null);
        if (!storeData)
            return;
        form.on('submit', function () {
            var objInput = this.elements['object'];
            var data = new StoreModule_1.StoreCartData();
            if (thisCartItems)
                for (var i = 0; i < thisCartItems.length; i++) {
                    var item = thisCartItems[i];
                    data.items.push(new StoreModule_1.StoreCartItem(item.name, item.sku, item.priceStr, item.price, item.quantity));
                }
            data.subTotalPrice = '' + scope.totals.subTotalPrice;
            data.taxPrice = '' + scope.totals.taxPrice;
            data.shippingPrice = '' + scope.totals.shippingPrice;
            data.totalPrice = '' + scope.totals.totalPrice;
            objInput.value = JSON.stringify(data);
            this.submit();
        });
        elem.find('.wb-store-form-buttons').eq(0).remove();
        elem.find('.wb-store-form').eq(0).show();
    }
    function register(app, storeData) {
        app.directive('paymentGateways', ['$http', function ($http) {
                return {
                    restrict: 'A',
                    scope: { 'paymentGateways': '=', 'totals': '=', 'onSubmit': '&' },
                    link: function (scope, element) {
                        if (scope.paymentGateways) {
                            element.find('.wb-store-pay-btn').each(function () {
                                initForm(scope, angular.element(this), storeData.checkoutUrl, $http);
                            });
                        }
                        else {
                            initInqueryFrom(scope, element, storeData);
                        }
                    }
                };
            }]);
    }
    exports.register = register;
});
define("store/js/StoreCart", ["require", "exports", "store/js/StoreCartElement", "store/js/StorePaymentFormsDirective"], function (require, exports, StoreCartElement_1, StorePaymentFormsDirective) {
    "use strict";
    exports.__esModule = true;
    exports.init = exports.StoreCartTotals = void 0;
    var StoreCartTotals = (function () {
        function StoreCartTotals() {
            this.subTotalPrice = new Big(0.0);
            this.totalPrice = new Big(0.0);
            this.taxPrice = new Big(0.0);
            this.shippingPrice = new Big(0.0);
        }
        return StoreCartTotals;
    }());
    exports.StoreCartTotals = StoreCartTotals;
    var StoreCartController = (function () {
        function StoreCartController($scope, $element, $http, cartUrl, data) {
            this.$element = $element;
            this.$http = $http;
            this.cartUrl = cartUrl;
            this.data = data;
            this.totals = new StoreCartTotals();
            this.loading = false;
            this.flowStep = 0;
            this.hideDeliveryInfo = true;
            this.billingInfoErrors = null;
            this.deliveryInfoErrors = null;
            this.generalErrors = null;
            this.shippingMethods = [];
            this.shippingMethod = null;
            this.countryIndex = {};
            this.destinationZonesCountriesAreEmpty = true;
            this.userAgreedToTerms = false;
            this.storeElem = null;
            this.anchorElem = null;
            var scope = $scope;
            scope.main = this;
            scope.filterAllowedCountries = this.filterAllowedCountries.bind(this);
            scope.filterAllowedRegions = this.filterAllowedRegions.bind(this);
            if (!data.currency)
                data.currency = { code: 'USD', postfix: '', prefix: '$' };
            if (!data.priceOptions)
                data.priceOptions = { decimalPoint: '.', decimalPlaces: 2, thousandsSeparator: '' };
            for (var _i = 0, _a = this.data.countries; _i < _a.length; _i++) {
                var country = _a[_i];
                this.countryIndex[country.code] = country;
            }
            for (var i in this.data.shippingRegionCodes) {
                if (this.data.shippingRegionCodes.hasOwnProperty(i)) {
                    this.destinationZonesCountriesAreEmpty = false;
                    break;
                }
            }
            for (var _b = 0, _c = this.data.items; _b < _c.length; _b++) {
                var item = _c[_b];
                item.quantityInput = item.quantity;
            }
            this.updateTotals();
        }
        StoreCartController.prototype.goBack = function () {
            if (this.flowStep > 0) {
                this.flowStep--;
                if (this.flowStep < 3 && !this.data.billingShippingRequired)
                    this.flowStep = 0;
            }
            else {
                location.href = this.data.backUrl;
            }
        };
        StoreCartController.prototype.checkout = function () {
            var _this = this;
            if (this.loading)
                return;
            if (this.data.minOrderPrice && this.totals.subTotalPrice.lt(this.data.minOrderPrice))
                return;
            if (this.flowStep == 0) {
                this.flowStep = this.data.billingShippingRequired ? 1 : 3;
            }
            else if (this.flowStep == 1 && this.data.billingShippingRequired) {
                this.loading = true;
                this.$http.post(this.cartUrl + 'billing-info', angular.toJson({
                    billingInfo: this.data.billingInfo,
                    deliveryInfo: this.data.deliveryInfo,
                    useSame: this.hideDeliveryInfo,
                    orderComment: this.data.orderComment,
                    userAgreedToTerms: this.userAgreedToTerms
                }))
                    .then(function (res) {
                    _this.data.billingInfo = res.data.billingInfo;
                    _this.data.deliveryInfo = res.data.deliveryInfo;
                    _this.billingInfoErrors = res.data.billingInfoErrors;
                    _this.deliveryInfoErrors = res.data.deliveryInfoErrors;
                    _this.generalErrors = res.data.generalErrors;
                    if (res.data.forceShowDeliveryInfo)
                        _this.hideDeliveryInfo = false;
                    _this.applyTotalsResponse(res.data);
                    if (!_this.billingInfoErrors && !_this.deliveryInfoErrors && !_this.generalErrors) {
                        _this.flowStep = 2;
                        _this.updateTotals();
                        var anchor = _this.getAnchorElem();
                        if (anchor.length)
                            window.scrollTo(0, anchor.offset().top);
                    }
                })["finally"](function () { _this.loading = false; })["catch"](function () { console.log('Error updating cart'); });
            }
            else if (this.flowStep == 2 && this.data.billingShippingRequired) {
                this.applyShipping(null, function () {
                    if (_this.shippingMethod || _this.shippingMethods.length == 0)
                        _this.flowStep = 3;
                });
            }
        };
        StoreCartController.prototype.onPaymentGatewayFormSubmit = function () {
            if (this.data.billingShippingRequired || !this.data.termsCheckboxEnabled)
                return true;
            if (this.userAgreedToTerms) {
                this.generalErrors = null;
                return true;
            }
            this.generalErrors = {
                __isEmpty: '',
                userAgreedToTerms: this.data.translations['You must agree to terms and conditions']
            };
            return false;
        };
        StoreCartController.prototype.getStoreElem = function () {
            if (this.storeElem === null) {
                this.storeElem = this.$element.parents('.wb-store').eq(0);
            }
            return this.storeElem;
        };
        StoreCartController.prototype.getAnchorElem = function () {
            if (this.anchorElem === null) {
                this.anchorElem = this.getStoreElem().children('.wb_anchor').eq(0);
            }
            return this.anchorElem;
        };
        StoreCartController.prototype.applyTotalsResponse = function (totals) {
            this.shippingMethods = totals.shippingMethods ? totals.shippingMethods : [];
            for (var _i = 0, _a = this.shippingMethods; _i < _a.length; _i++) {
                var method = _a[_i];
                if (method.id == totals.shippingMethodId) {
                    this.shippingMethod = method;
                    break;
                }
            }
            this.totals.subTotalPrice = new Big(totals.subTotalPrice);
            this.totals.shippingPrice = new Big(totals.shippingPrice);
            this.totals.taxPrice = new Big(totals.taxPrice);
            this.totals.totalPrice = new Big(totals.totalPrice);
        };
        StoreCartController.prototype.applyShipping = function (shippingMethod, callback) {
            var _this = this;
            if (shippingMethod)
                this.shippingMethod = shippingMethod;
            this.loading = true;
            this.$http.post(this.cartUrl + 'calc-totals', angular.toJson({
                shippingMethodId: (this.shippingMethod ? this.shippingMethod.id : 0)
            }))
                .then(function (res) {
                _this.applyTotalsResponse(res.data);
                _this.updateTotals();
                if (typeof callback === 'function')
                    callback.call(_this);
            })["finally"](function () { _this.loading = false; })["catch"](function () { console.log('Error updating cart'); });
        };
        StoreCartController.prototype.changeInfoField = function (field, errorSource) {
            if (!errorSource)
                return;
            if (field in errorSource) {
                delete errorSource[field];
            }
            var isEmpty = true;
            for (var k in errorSource) {
                if (k === '__isEmpty')
                    continue;
                isEmpty = false;
                break;
            }
            errorSource.__isEmpty = isEmpty ? '1' : '';
        };
        StoreCartController.prototype.getCountry = function (code) {
            return (code && this.countryIndex.hasOwnProperty(code)) ? this.countryIndex[code] : null;
        };
        StoreCartController.prototype.filterAllowedCountries = function (country, index, countries) {
            return this.destinationZonesCountriesAreEmpty || this.data.shippingRegionCodes.hasOwnProperty(country.code);
        };
        StoreCartController.prototype.filterAllowedRegions = function (countryCode) {
            var _this = this;
            return function (region, index, regions) {
                return _this.destinationZonesCountriesAreEmpty || (_this.data.shippingRegionCodes.hasOwnProperty(countryCode) && (_this.data.shippingRegionCodes[countryCode].length == 0 || _this.data.shippingRegionCodes[countryCode].indexOf(region.code) >= 0));
            };
        };
        StoreCartController.prototype.removeItem = function (item) {
            var _this = this;
            var idx = this.data.items.indexOf(item);
            if (idx >= 0)
                this.data.items.splice(idx, 1);
            this.updateTotals();
            this.loading = true;
            this.$http.get(this.cartUrl + 'remove/' + item.id)
                .then(function (res) {
                StoreCartElement_1.updateCartCounter(res.data.total);
            })["finally"](function () { _this.loading = false; })["catch"](function () { console.log('Error removing from cart'); });
        };
        StoreCartController.prototype.changeQuantity = function (item) {
            var _this = this;
            this.updateTotals();
            this.loading = true;
            this.$http.get(this.cartUrl + 'update/' + item.id + '/' + item.quantity)
                .then(function (res) {
                StoreCartElement_1.updateCartCounter(res.data.total);
            })["finally"](function () { _this.loading = false; })["catch"](function () { console.log('Error updating cart'); });
        };
        StoreCartController.prototype.updateTotals = function () {
            var dp = parseInt('' + this.data.priceOptions.decimalPlaces, 10);
            this.totals.subTotalPrice = new Big(0.0);
            for (var _i = 0, _a = this.data.items; _i < _a.length; _i++) {
                var item = _a[_i];
                var quantityStr = '' + item.quantityInput;
                var quantity = parseInt(quantityStr, 10);
                if (isNaN(quantity) || quantity < 1) {
                    item.quantity = quantity = 1;
                }
                else if (!quantityStr.match(/^[0-9]+$/)) {
                    item.quantity = quantity;
                }
                else {
                    item.quantity = item.quantityInput;
                }
                var itemTotalPrice = (new Big(quantity)).times(item.price);
                item.totalPriceStr = this.formatPrice(itemTotalPrice);
                this.totals.subTotalPrice = this.totals.subTotalPrice.plus(itemTotalPrice);
            }
            this.totals.subTotalPrice = this.totals.subTotalPrice.round(dp);
            this.totals.totalPrice = (new Big(0.0))
                .plus(this.totals.subTotalPrice)
                .plus(this.totals.shippingPrice)
                .plus(this.totals.taxPrice)
                .round(dp);
        };
        StoreCartController.prototype.fmtSMN = function (item, units) {
            var estTimeStr = '', estTime = item.estimatedShippingTime;
            if (estTime && (estTime.from > 0 || estTime.to > 0)) {
                if (estTime.from === estTime.to) {
                    estTimeStr = '' + estTime.from;
                }
                else {
                    estTimeStr = '' + estTime.from + ' - ' + estTime.to;
                }
                estTimeStr = ' (' + estTimeStr + ' ' + units + ')';
            }
            return item.name + estTimeStr;
        };
        StoreCartController.prototype.formatPrice = function (price) {
            if (!this.data.currency || !this.data.priceOptions)
                return ('' + price);
            var point = this.data.priceOptions.decimalPoint;
            var places = this.data.priceOptions.decimalPlaces;
            price = (price + '').replace(/[^0-9+\-Ee.]/g, '');
            var n = !isFinite(+price) ? 0 : +price;
            var prec = !isFinite(+places) ? 0 : Math.abs(places);
            var sep = (typeof this.data.priceOptions.thousandsSeparator === 'undefined') ? '' : this.data.priceOptions.thousandsSeparator;
            var dec = (typeof point === 'undefined') ? '.' : point;
            var toFixedFix = function (n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
            var s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
            if (s[0].length > 3) {
                s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
            }
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || '';
                s[1] += new Array(prec - s[1].length + 1).join('0');
            }
            price = s.join(dec);
            return (this.data.currency.prefix + price + this.data.currency.postfix);
        };
        return StoreCartController;
    }());
    function init(storeElementId, cartUrl, storeData) {
        var app = angular.module('StoreCart', []);
        StorePaymentFormsDirective.register(app, storeData);
        app.controller('StoreCartCtrl', [
            '$scope', '$element', '$http',
            function ($scope, $element, $http) { new StoreCartController($scope, $element, $http, cartUrl, storeData); }
        ]);
        angular.element(function () {
            var elem = document.getElementById(storeElementId);
            if (elem)
                angular.bootstrap(elem, ['StoreCart']);
        });
    }
    exports.init = init;
});
define("store/js/StoreDetails", ["require", "exports", "store/js/StoreCartElement"], function (require, exports, StoreCartElement_2) {
    "use strict";
    exports.__esModule = true;
    exports.init = void 0;
    function init(storeElementId, itemId, cartUrl, imageItems, translations) {
        var thisCartUrl = cartUrl, thisItemId = itemId, details = $('#' + storeElementId).eq(0), trAdded = (translations && ('Added!' in translations)) ? translations['Added!'] : null;
        initImageGallery(details, imageItems);
        details.find('.wb-store-cart-add-btn').eq(0).on('click', function () {
            var btn = $(this);
            var qtyElem = details.find(".wb-store-cart-add-quantity");
            var qtyGrp = qtyElem.closest(".form-group");
            var quantity = parseInt(qtyElem.val());
            qtyGrp.removeClass("has-error");
            if (isNaN(quantity) || quantity < 1) {
                qtyGrp.addClass("has-error");
                return;
            }
            btn.attr('disabled', 'disabled');
            StoreCartElement_2.addToCart(thisCartUrl, thisItemId, quantity, function (cartData) {
                if (cartData.total && trAdded) {
                    var oldText_1 = btn.html();
                    btn.html('<span class="fa fa-check-circle"></span> ' + trAdded);
                    setTimeout(function () { return btn.html(oldText_1); }, 1000);
                }
            }, function (cartData) {
                var timer = (cartData && cartData.total && trAdded) ? 1000 : 250;
                setTimeout(function () { return btn.removeAttr('disabled'); }, timer);
            });
        });
        var inquiryBtn = details.find('.wb-store-form-buttons').eq(0).find('.wb-store-inquiry-btn').eq(0);
        if (inquiryBtn.length === 0)
            return;
        var form = details.find('.wb-store-form').eq(0);
        inquiryBtn.on('click', function () {
            inquiryBtn.parent().hide();
            form.show();
        });
    }
    exports.init = init;
    function initImageGallery(details, items) {
        var thisElem = $('body > .pswp').eq(0), thisItems = items;
        details.find('.wb-store-image').css({ cursor: 'pointer' }).on('click', function () {
            if ($(this).attr("href") !== "javascript:void(0)")
                return;
            var selIndex = 0, alts = details.find('.wb-store-alt-images').eq(0).find('.wb-store-alt-img');
            for (var i = 0, c = alts.length; i < c; i++) {
                if (alts.eq(i).hasClass('active'))
                    break;
                selIndex++;
            }
            var loaded = 0, item;
            var onGalleryImgReady = function () {
                loaded++;
                if (loaded < thisItems.length)
                    return;
                (new PhotoSwipe(thisElem[0], PhotoSwipeUI_Default, thisItems, { index: selIndex })).init();
            };
            for (var i = 0, c = thisItems.length; i < c; i++) {
                item = thisItems[i];
                if (!item.w || !item.h) {
                    (function (item) {
                        var img = new Image();
                        img.onload = function () {
                            item.w = this.width;
                            item.h = this.height;
                            onGalleryImgReady();
                        };
                        img.src = item.src;
                    })(item);
                }
                else {
                    onGalleryImgReady();
                }
            }
        });
        details.find('.wb-store-alt-img').on('click', function () {
            if ($(this).hasClass('active'))
                return;
            var mainImgCont = $(this).parents('.wb-store-imgs-block').children('.wb-store-image'), mainImg = mainImgCont.children('img'), thumbImg = $(this).children('img'), newImg;
            $(this).parent().children('div').removeClass('active');
            $(this).addClass('active');
            newImg = thumbImg.clone().css('opacity', 0);
            mainImgCont.prepend(newImg);
            mainImg.css('opacity', 0);
            newImg.css('opacity', 1);
            var link = newImg.attr("data-link");
            var target = newImg.attr("data-target");
            if (link === "" || link === "http://" || link === "https://") {
                link = "javascript:void(0)";
                target = "_self";
            }
            if (target === "")
                target = "_self";
            mainImgCont.attr('href', link);
            mainImgCont.attr('target', target);
            setTimeout(function () { mainImg.remove(); }, 300);
        });
        details.find('.wb-store-alt-images > span').on('click', function () {
            var altImgsCont = $(this).parents('.wb-store-alt-images'), imgList = altImgsCont.find('.wb-store-alt-img'), offsetWidth = imgList.eq(0).outerWidth(true), offsetCont = imgList.eq(0).parent(), currOffset = ((/margin-left:[^-\d]*(-*\d+)[^\d]*/i.test(offsetCont.attr('style'))) ? parseInt(RegExp.$1) : 0), maxImgsInRow = Math.min(offsetCont.parent().width() / offsetWidth, imgList.length), offset;
            var leftOffsetLim = 0;
            var rightOffsetLim = -((imgList.length - maxImgsInRow) * offsetWidth);
            if ($(this).hasClass('arrow-left')) {
                offset = Math.min((currOffset + offsetWidth), leftOffsetLim);
            }
            else if ($(this).hasClass('arrow-right')) {
                offset = Math.max((currOffset - offsetWidth), rightOffsetLim);
            }
            offsetCont.css('margin-left', offset + 'px');
        });
    }
});
define("store/js/StoreList", ["require", "exports", "store/js/StoreCartElement"], function (require, exports, StoreCartElement_3) {
    "use strict";
    exports.__esModule = true;
    exports.init = void 0;
    function init(storeElementId, cartUrl, urlAnchor, translations) {
        var store = $('#' + storeElementId).eq(0);
        var storeList = store.find('.wb-store-list').eq(0);
        var storeItems = storeList.find('.wb-store-item');
        var storeFilter = store.find('.wb-store-cat-select');
        var itemDrag = null, cancelDrag, thisCartUrl = cartUrl;
        var addToCartButtons = storeItems.find('.wb-store-item-add-to-cart');
        var buyNowButtons = storeItems.find('.wb-store-item-buy-now');
        var trAdded = (translations && ('Added!' in translations)) ? translations['Added!'] : null;
        storeFilter.on('change', function () {
            location.href = $(this.options[this.selectedIndex]).data('store-url');
        });
        storeItems.on('mousedown', function (e) {
            var item = $(this), offset = item.offset();
            itemDrag = {
                x: (e.pageX - offset.left),
                y: (e.pageY - offset.top),
                mX: e.pageX,
                mY: e.pageY,
                id: item.data('item-id'),
                elem: item,
                helper: null,
                helperContainer: null
            };
            e.stopPropagation();
            e.preventDefault();
            return false;
        }).on('mouseup', cancelDrag = function (e) {
            if (itemDrag) {
                for (var i = 0; i < StoreCartElement_3.cartElenents.length; i++) {
                    var offset = StoreCartElement_3.cartElenents[i].elem.offset();
                    var width = StoreCartElement_3.cartElenents[i].elem.width();
                    var height = StoreCartElement_3.cartElenents[i].elem.height();
                    if (e.pageX >= offset.left && e.pageX <= (offset.left + width)
                        && e.pageY >= offset.top && e.pageY <= (offset.top + height)) {
                        StoreCartElement_3.addToCart(thisCartUrl, itemDrag.id);
                        break;
                    }
                }
                if (itemDrag.helperContainer) {
                    itemDrag.helperContainer.remove();
                    delete itemDrag.helper;
                    delete itemDrag.helperContainer;
                }
                itemDrag = null;
                e.preventDefault();
                return false;
            }
            return;
        });
        addToCartButtons.on('click', function (e) {
            var btn = $(this);
            e.stopImmediatePropagation();
            e.preventDefault();
            addToCartButtons.attr('disabled', 'disabled');
            buyNowButtons.attr('disabled', 'disabled');
            StoreCartElement_3.addToCart(thisCartUrl, btn.closest('.wb-store-item').data('itemId'), 1, function (cartData) {
                if (cartData.total && trAdded) {
                    var oldText_2 = btn.html();
                    btn.html('<span class="fa fa-check-circle"></span> ' + trAdded);
                    setTimeout(function () { return btn.html(oldText_2); }, 1000);
                }
            }, function (cartData) {
                addToCartButtons.each(function () {
                    if (this === btn.get(0)) {
                        var timer = (cartData && cartData.total && trAdded) ? 1000 : 250;
                        setTimeout(function () { return btn.removeAttr('disabled'); }, timer);
                    }
                    else {
                        $(this).removeAttr('disabled');
                    }
                });
                buyNowButtons.removeAttr('disabled');
            });
        });
        buyNowButtons.on('click', function (e) {
            e.stopImmediatePropagation();
            e.preventDefault();
            addToCartButtons.attr('disabled', 'disabled');
            buyNowButtons.attr('disabled', 'disabled');
            StoreCartElement_3.addToCart(thisCartUrl, $(this).closest('.wb-store-item').data('itemId'), 1, function () {
                window.location.href = thisCartUrl + urlAnchor;
            }, function () {
                addToCartButtons.removeAttr('disabled');
                buyNowButtons.removeAttr('disabled');
            });
        });
        $(document.body).on('mousemove', function (e) {
            if (!itemDrag)
                return;
            var a = (itemDrag.mX - e.pageX), b = (itemDrag.mY - e.pageY), dist = Math.sqrt(a * a + b * b);
            if (dist < 4)
                return;
            if (!itemDrag.helperContainer) {
                var dragElem = itemDrag.elem.clone().css({ margin: 0 });
                dragElem.find('.wb-store-remove-from-helper').remove();
                if (dragElem.is('tr')) {
                    dragElem = itemDrag.elem.closest('table').clone().empty().append(dragElem);
                }
                itemDrag.helperContainer = $('<div>').css({
                    position: 'absolute',
                    left: 0,
                    top: 0,
                    right: 0,
                    bottom: 0,
                    overflow: 'hidden'
                }).appendTo(document.body);
                itemDrag.helper = $('<div>')
                    .addClass('wb-store-list wb-store-drag-helper')
                    .css({
                    position: 'absolute',
                    zIndex: 99999,
                    left: 0, top: 0,
                    width: itemDrag.elem.width() + 10,
                    height: itemDrag.elem.height() + 10
                })
                    .append(dragElem)
                    .appendTo(itemDrag.helperContainer);
            }
            var rect = document.body.getBoundingClientRect();
            itemDrag.helperContainer.css({
                width: rect.width,
                height: rect.height
            });
            itemDrag.helper.css({
                left: (e.pageX - itemDrag.x),
                top: (e.pageY - itemDrag.y)
            });
            e.preventDefault();
            return false;
        });
        $(document.body).on('mouseup', cancelDrag);
    }
    exports.init = init;
});
