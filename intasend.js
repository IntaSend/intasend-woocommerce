function bindEvent(element, eventName, eventHandler) {
    if (element.addEventListener) {
        element.addEventListener(eventName, eventHandler, false);
    } else if (element.attachEvent) {
        element.attachEvent('on' + eventName, eventHandler);
    }
}

var successCallback = function (data) {
    var checkout_form = window.jqInstance('form.woocommerce-checkout');

    // add a tracking to hidden input field
    checkout_form.append("<input type='hidden' id='intasend_tracking_id' name='intasend_tracking_id' value='" + data.tracking_id + "'/>");

    // deactivate the paymentRequest function event
    checkout_form.off('checkout_place_order', paymentRequest);

    // submit the form now
    checkout_form.submit();

};

var errorCallback = function (data) {
    console.log(data);
};

var paymentRequest = function () {
    let phone_number = ""
    let name = ""
    let email = ""
    let comments = ""
    let form = window.jqInstance('form.woocommerce-checkout');
    let public_key = window.intasend_params.public_key
    let testmode = window.intasend_params.testmode
    let amount = window.intasend_params.total
    let currency = window.intasend_params.currency
    let api_ref = window.intasend_params.api_ref
    let live = true
    if (testmode) {
        live = false
    }
    try {
        phone_number = form.find("#customer_details").find("#billing_phone").val()
        email = form.find("#customer_details").find("#billing_email").val()
        comments = form.find("#order_comments")
        first_name = form.find("#customer_details").find("#billing_first_name").val()
        last_name = form.find("#customer_details").find("#billing_last_name").val()

        name = first_name + " " + last_name

        if (phone_number) {
            phone_number = phone_number.toString().replace(/\s/g, '')
            if (phone_number.startsWith("0")) {
                phone_number = phone_number.substr(1)
                phone_number = "254" + phone_number
            } else if (phone_number.startsWith("+")) {
                phone_number = phone_number.substr(1)
            }
        }
        if (amount) {
            amount = parseFloat(amount.replace(/\D/g, ''))
        }

    } catch (error) {
        return false
    }

    window.IntaSend.setup({
        publicAPIKey: public_key,
        live: live
    })

    window.IntaSend.run({
        "amount": amount,
        "phone_number": phone_number,
        "api_ref": api_ref,
        "email": email,
        "name": name,
        "currency": currency,
        "comments": comments
    })


    bindEvent(window, 'message', function (e) {
        if (e.data.message.identitier === 'intasend-status-update-cdrtl') {
            if (e.data.message.state === "COMPLETE") {
                return successCallback({
                    "tracking_id": e.data.message.tracking_id
                })
            }
        }
    });
    return false;
};

jQuery(function ($) {
    var checkout_form = $('form.woocommerce-checkout');
    window.jqInstance = $
    window.intasend_params = intasend_params
    checkout_form.on('checkout_place_order', paymentRequest);
});