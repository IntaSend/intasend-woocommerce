let agents = require("./agents")


const IntaSend = {

    _redirectURL: "",

    _publicAPIKey: "",

    _element: "tp_button",

    _btnElement: Object,

    _dataset: Object,

    _live: true,

    setup(obj) {

        IntaSend._publicAPIKey = obj.publicAPIKey

        IntaSend._redirectURL = obj.redirectURL

        IntaSend._live = obj.live

        IntaSend._element = obj.element || IntaSend._element


        IntaSend._btnElement = document.getElementsByClassName(IntaSend._element)

        if (IntaSend._btnElement) {

            for (let i = 0; i < IntaSend._btnElement.length; ++i) {

                let btn = IntaSend._btnElement[i]

                btn.addEventListener('click', function () {

                    let dataset = btn.dataset

                    IntaSend.loadPaymentModal(dataset)

                })

            }

        }


        // HANDLE NEW MESSAGE NOTIFICATIONS

        function bindEvent(element, eventName, eventHandler) {

            if (element.addEventListener) {

                element.addEventListener(eventName, eventHandler, false);

            } else if (element.attachEvent) {

                element.attachEvent('on' + eventName, eventHandler);

            }

        }


        bindEvent(window, 'message', function (e) {

            if (e.data.message) {

                if (e.data.message.identitier == 'intasend-status-update-cdrtl') {

                    if (e.data.message.state === "COMPLETED") {

                        if (IntaSend._redirectURL) {

                            window.location.href = IntaSend._redirectURL

                        }

                    }

                }

            }

        });

        // End message events


        return IntaSend

    },

    run(obj) {

        let dataset = {}

        if (obj.name) dataset.name = obj.name

        if (obj.amount) dataset.amount = obj.amount

        if (obj.phone_number) dataset.phone_number = obj.phone_number

        if (obj.email) dataset.email = obj.email

        if (obj.comment) dataset.comment = obj.comment

        if (obj.api_ref) dataset.api_ref = obj.api_ref

        if (obj.currency) dataset.currency = obj.currency

        IntaSend.loadPaymentModal(dataset)

    },

    loadPaymentModal(dataset) {

        dataset.callback_url = IntaSend._redirectURL

        dataset.public_key = IntaSend._publicAPIKey

        dataset.host = window.location.protocol + "://" + window.location.host


        IntaSend.clearElements()

        let modalContent = IntaSend.prepareModal()

        IntaSend.closeModalIcon(modalContent)

        let iframe = IntaSend.prepareFrame(modalContent, dataset)

        return iframe

    },

    clearElements() {

        let iframes = document.querySelectorAll('iframe');

        for (let i = 0; i < iframes.length; i++) {

            iframes[i].parentNode.removeChild(iframes[i]);

        }

        // Remove modals

        let modals = document.querySelectorAll('modal');

        for (let x = 0; x < modals.length; x++) {

            modals[x].parentNode.removeChild(modals[x]);

        }

    },

    prepareModal() {

        let is_mobile = agents.MobileCheck()

        let modal = document.createElement("modal");

        modal.style.display = "flex"

        modal.style.position = "fixed"

        modal.style.zIndex = 1200

        modal.style.left = 0

        modal.style.top = 0

        modal.style.width = "100%"

        modal.style.height = "100%"

        modal.style.overflow = "auto"

        modal.style.backgroundColor = "rgb(0,0,0)"

        modal.style.backgroundColor = "rgba(0,0,0,0.7)"


        document.body.appendChild(modal);


        let modalContent = document.createElement("modal-content")

        if (is_mobile) {

            modalContent.style.width = "98%";

        } else {

            modalContent.style.width = "380px";

        }

        modalContent.style.height = "auto";

        modalContent.style.margin = "auto"

        modalContent.style.display = "block"

        modalContent.style.paddingTop = "20px"

        modalContent.style.backgroundColor = "transparent"

        modal.appendChild(modalContent)

        return modalContent

    },

    closeModalIcon(modalContent) {

        let iconHolder = document.createElement("div")

        let icon = document.createElement("div")

        icon.innerHTML = IntaSend._closeIconSVG()

        icon.style.cursor = "pointer"

        icon.style.marginTop = "-5px"

        iconHolder.style.float = "right"

        iconHolder.style.display = "block"

        iconHolder.style.height = "10px"

        iconHolder.style.zIndex = 1250

        iconHolder.appendChild(icon)

        modalContent.appendChild(iconHolder)


        icon.addEventListener('click', function () {

            IntaSend.clearElements()

        })

    },

    prepareFrame(modalContent, dataset) {

        let params = new URLSearchParams(dataset).toString()

        let ifrm = document.createElement("iframe");

        if (IntaSend._live) {

            ifrm.setAttribute("src", "https://websdk.intasend.com/?" + params);

        } else {

            ifrm.setAttribute("src", "https://websdk-sandbox.intasend.com/?" + params);

        }

        ifrm.style.width = "100%";

        ifrm.style.minHeight = "570px";

        ifrm.style.border = 0;

        ifrm.frameborder = 0

        ifrm.scrolling = "no"


        modalContent.appendChild(ifrm)

        return ifrm

    },

    _closeIconSVG() {

        return '<svg height="10pt" fill="#999" viewBox="0 0 329.26933 329" width="10pt" xmlns="http://www.w3.org/2000/svg"><path d="m194.800781 164.769531 128.210938-128.214843c8.34375-8.339844 8.34375-21.824219 0-30.164063-8.339844-8.339844-21.824219-8.339844-30.164063 0l-128.214844 128.214844-128.210937-128.214844c-8.34375-8.339844-21.824219-8.339844-30.164063 0-8.34375 8.339844-8.34375 21.824219 0 30.164063l128.210938 128.214843-128.210938 128.214844c-8.34375 8.339844-8.34375 21.824219 0 30.164063 4.15625 4.160156 9.621094 6.25 15.082032 6.25 5.460937 0 10.921875-2.089844 15.082031-6.25l128.210937-128.214844 128.214844 128.214844c4.160156 4.160156 9.621094 6.25 15.082032 6.25 5.460937 0 10.921874-2.089844 15.082031-6.25 8.34375-8.339844 8.34375-21.824219 0-30.164063zm0 0"/></svg>'

    }

}


window.IntaSend = IntaSend;

module.exports = IntaSend;