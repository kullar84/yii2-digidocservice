/**
 * This is the example web application that demonstrates how to handle hashcode containers together with hashcode
 * PHP library and DigiDocService.
 *
 * Current file is the javascript helper for the hashcode application.
 *
 * LICENSE:
 *
 * This library is free software; you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General
 * Public License as published by the Free Software Foundation;
 * either version 2.1 of the License, or (at your option) any
 * later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @version	   1.0.0
 * @author     Tarmo Kalling <tarmo.kalling@nortal.com>
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 */

"use strict";

function http_post(path, params, method) {
    method = method || "post"; // Set method to post by default if not specified.

    // The rest of this code assumes you are not using a library.
    // It can be made less wordy if you use one.
    var form = document.createElement("form");
    form.setAttribute("method", method);
    form.setAttribute("action", path);
	
	var csrf = $("meta[name=csrf-token]");
	if(csrf.length > 0){
		var hiddenField = document.createElement("input");
            hiddenField.setAttribute("type", "hidden");
            hiddenField.setAttribute("name", "_csrf");
            hiddenField.setAttribute("value",csrf.attr("content"));
		form.appendChild(hiddenField);
	}

    for (var key in params) {
        if (params.hasOwnProperty(key)) {
            var hiddenField = document.createElement("input");
            hiddenField.setAttribute("type", "hidden");
            hiddenField.setAttribute("name", key);
            hiddenField.setAttribute("value", params[key]);

            form.appendChild(hiddenField);
        }
    }

    document.body.appendChild(form);
    form.submit();
}

function show_local_signing_error(ex) {
    var error_message;
    if (ex instanceof IdCardException) {
        error_message = '[Error code: ' + ex.returnCode + '; Message: ' + ex.message + ']';
    } else {
        error_message = ex.message != undefined ? ex.message : ex;
    }
    jQuery('#idSignModalErrorContainer').html(error_message);
    jQuery('#idSignModalErrorContainer').show();
}

function show_signing_error(ex, signatureId) {
    var error_message;
    if (ex instanceof IdCardException) {
        error_message = '[Error code: ' + ex.returnCode + '; Message: ' + ex.message + ']';
    } else {
        error_message = ex.message != undefined ? ex.message : ex;
    }

    var params = {
        request_act: 'ID_SIGN_COMPLETE',
        error_message: error_message
    };
    if (signatureId) {
        params.signature_id = signatureId;
    }
    http_post('', params);
}

/*
 * Logic for asynchronous and synchronous requests that need JavaScripts help.
 */
var ee = ee == undefined ? {} : ee;
ee.sk = ee.sk == undefined ? {} : ee.sk;
ee.sk.hashcode = ee.sk.hashcode == undefined ? {} : ee.sk.hashcode;

ee.sk.hashcode = {
    DownloadContainer: function () {
        http_post('', {
            request_act: 'DOWNLOAD'
        });
    },

    RemoveDataFile: function (datafileId, datafileName) {
        http_post('', {
            request_act: 'REMOVE_DATA_FILE',
            datafileId: datafileId,
            datafileName: datafileName
        });
    },

    RemoveSignature: function (signatureId) {
        http_post('', {
            request_act: 'REMOVE_SIGNATURE',
            signatureId: signatureId
        });
    },

    StartMobileSign: function () {
        $('#mobileSignErrorContainer').hide();
        var phoneNo = jQuery('#mid_PhoneNumber').val();
        var idCode = jQuery('#mid_idCode').val();
        if (!phoneNo) {
            $('#mobileSignErrorContainer')
                .html('Phone number is mandatory!')
                .show();
        } else if (!idCode) {
            $('#mobileSignErrorContainer')
                .html('Social security number is mandatory!')
                .show();
        } else {
            jQuery.post(
                '',
                {
                    request_act: 'MID_SIGN',
                    phoneNo: phoneNo,
                    idCode: idCode,
                    subAct: 'START_SIGNING'
                }
            ).done(function (resp) {
                    if (resp["error_message"]) {
                        jQuery('#mobileSignErrorContainer').html('There was an error initiating ' +
                            'MID signing: ' + resp['error_message'])
                        jQuery('#mobileSignErrorContainer').show();
                    } else {
                        jQuery('#mobileSignModalHeader').hide();
                        jQuery('#mobileSignModalFooter').hide();
                        var challenge = resp['challenge'];
                        jQuery('.mobileSignModalContent').html('<table><tr><td style="width: 75%;">' +
                            '<h4>Sending digital signing request to phone is in progress ...</h4>' +
                        '<p style="font-size: 12px;">Make sure control code matches with one in the phone screen and enter Mobile-ID PIN2. ' +
                        'After you enter signing PIN, a digital signature is created to the document, which may bind you to legal liabilities. ' +
                        'You must therefore agree to the content of the document. When in doubt, please go back and check document contents.</p></td>' +
                        '<td style="vertical-align: middle; text-align: center;">' +
                            'Control code:' +
                            '<h2>' + challenge + '</h2>' +
                        '</td></tr></table>');
                        var intervalId = setInterval(function () {
                            jQuery.post(
                                '',
                                {
                                    request_act: 'MID_SIGN',
                                    subAct: 'GET_SIGNING_STATUS'
                                }
                            ).done(function (status_resp) {
                                    if (status_resp["is_success"] == true) {
                                        clearInterval(intervalId);
                                        http_post('', {
                                            request_act: 'MID_SIGN_COMPLETE'
                                        });
                                    } else if (!!status_resp['error_message']) {
                                        clearInterval(intervalId);
                                        http_post('', {
                                            request_act: 'MID_SIGN_COMPLETE',
                                            error_message: status_resp['error_message']
                                        });
                                    }
                                }).fail(function (data) {
                                    clearInterval(intervalId);
                                    http_post('', {
                                        request_act: 'MID_SIGN_COMPLETE',
                                        error_message: data.status + '-' + data.statusText
                                    });
                                })
                        }, 3000);
                    }
                }).fail(function (data) {
                    jQuery('#mobileSignErrorContainer').html('There was an error performing AJAX request to initiate ' +
                        'MID signing: ' + data.status + '-' + data.statusText)
                    jQuery('#mobileSignErrorContainer').show();
                });
        }

    },

    // ID card signing methods
    // Please read: https://github.com/open-eid/js-token-signing/wiki/ModernAPI
    //
    // There You will have very good overview of API and much more compact example of signing using JavaScript
    errorHandler: function (reason) {
        var longMessage = 'ID-card siging: ',
            $errorContainer = $('#idSignModalErrorContainer');

        $errorContainer.text('').hide();
        console.log('inside error handler');
        var hwcrypto = window.hwcrypto;
        switch (reason.message) {

            case 'no_backend':
                longMessage += 'Cannot find ID-card browser extensions';
                break;
            case hwcrypto.USER_CANCEL:
                longMessage += 'Signing canceled by user';
                break;
            case hwcrypto.INVALID_ARGUMENT:
                longMessage += 'Invalid argument';
                break;
            case hwcrypto.NO_CERTIFICATES_FOUND:
                longMessage += ' Failed reading ID-card certificates make sure. ' +
                    'ID-card reader or ID-card is inserted correctly';
                break;
            case hwcrypto.NO_IMPLEMENTATION:
                longMessage += ' Please install or update ID-card Utility or install missing browser extension. ' +
                    'More information about on id.installer.ee';
                break;
            case hwcrypto.TECHNICAL_ERROR:
            default:
                longMessage += 'Unknown technical error occurred'
        }

        $errorContainer.text(longMessage).show();
        console.log('exiting error handler...');
    },

    hashCreateResponseHandler: function (status_resp, lang, cert) {
        var self = this;
        if (status_resp["is_success"] == true) {
            var signatureDigest = status_resp['signature_info_digest'],
                signatureID = status_resp['signature_id'],
                signatureHashType = status_resp['signature_hash_type'];

            window.hwcrypto
                .sign(cert, {
                    hex: signatureDigest,
                    type: signatureHashType,
                }, {lang: 'en'})
                .then(function (signature) {
                     http_post('', {
                        request_act: 'ID_SIGN_COMPLETE',
                        signature_id: signatureID,
                        signature_value: signature.hex
                    });
                }, function (reason) {
                    console.log('error occurred when started signing document');
                    self.errorHandler(reason);
                });
        } else if (!!status_resp['error_message']) {
            http_post('', {
                request_act: 'ID_SIGN_COMPLETE',
                error_message: status_resp['error_message']
            });
        }
    },

    prepareSigningParameters: function (cert) {
        var role = $('#idSignRole').val(),
            city = $('#idSignCity').val(),
            state = $('#idSignState').val(),
            postalCode = $('#idSignPostalCode').val(),
            country = $('#idSignCountry').val();

        var id_sign_create_hash_req_params = {
            request_act: 'ID_SIGN_CREATE_HASH',
            signersCertificateHEX: cert.hex
        };

        if (role) {
            id_sign_create_hash_req_params.signersRole = role;
        }

        if (city) {
            id_sign_create_hash_req_params.signersCity = city;
        }

        if (state) {
            id_sign_create_hash_req_params.signersState = state;
        }

        if (postalCode) {
            id_sign_create_hash_req_params.signersPostalCode = postalCode;
        }

        if (country) {
            id_sign_create_hash_req_params.signersCountry = country;
        }

        return id_sign_create_hash_req_params;
    },

    IDCardSign: function () {
        $('#idSignModalErrorContainer').hide();
        var self = this;
        var lang = 'en';

        window.hwcrypto.getCertificate({lang: lang}).then(function (cert) {
            var id_sign_create_hash_req_params = self.prepareSigningParameters(cert);
            $.post('', id_sign_create_hash_req_params)
                .done(function (status_resp) {
                    self.hashCreateResponseHandler(status_resp, lang, cert);
                })
                .fail(function (data) {
                    http_post('', {
                        request_act: 'ID_SIGN_COMPLETE',
                        error_message: data.status + '-' + data.statusText
                    });
                });
        }, function (reason) {
            console.log('error occured when getting certificate');
            self.errorHandler(reason);
        });

    }
};