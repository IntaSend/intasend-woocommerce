function bindEvent(element, eventName, eventHandler) {
    if (element.addEventListener) {
        element.addEventListener(eventName, eventHandler, false);
    } else if (element.attachEvent) {
        element.attachEvent('on' + eventName, eventHandler);
    }
}

var successCallback = function (data) {
    var checkout_form = window.jqInstance('form.woocommerce-checkout');
    console.log("successCallback: checkout_form", checkout_form)

    // add a tracking to hidden input field
    checkout_form.find('#intasend_tracking_id').val(data.tracking_id);

    // deactivate the paymentRequest function event
    checkout_form.off('checkout_place_order', paymentRequest);

    // submit the form now
    checkout_form.submit();

};

var errorCallback = function (data) {
    console.log(data);
};

var paymentRequest = function () {
    let amount = 0
    let phone_number = ""
    let name = ""
    let email = ""
    let api_ref = "woostore 1"
    let form = window.jqInstance('form.woocommerce-checkout');
    try {
        console.log(this)
        amount = form.find(".order-total").find(".amount").text()
        phone_number = form.find("#customer_details").find("#billing_phone").val()
        email = form.find("#customer_details").find("#billing_email").val()
        first_name = form.find("#customer_details").find("#billing_first_name").val()
        last_name = form.find("#customer_details").find("#billing_last_name").val()
        name = first_name + " " + last_name

        if (phone_number) {
            if (phone_number.toString().startsWith("0")) {
                phone_number = phone_number.substr(1)
                phone_number = "254" + phone_number
            }
        }
        if (amount) {
            amount = parseFloat(amount.replace(/\D/g, ''))
        }
        console.log(amount)

    } catch (error) {
        console.log(error)
    }

    window.IntaSend.setup({
        publicAPIKey: "TPPublicKey_91ffc81a-8ac4-419e-8008-7091caa8d73f",
        live: false
    })

    window.IntaSend.run({
        "amount": amount,
        "phone_number": phone_number,
        "api_ref": api_ref,
        "email": email,
        "name": name
    })

    bindEvent(window, 'message', function (e) {
        if (e.data.message == 'tp-on-complete-event') {
            return successCallback({
                "tracking_id": "is-tracking-id-tracking_id"
            })
        }
    });
    return false;
};

jQuery(function ($) {
    var checkout_form = $('form.woocommerce-checkout');
    console.log("Jquery block", checkout_form)
    window.jqInstance = $
    checkout_form.on('checkout_place_order', paymentRequest);
});