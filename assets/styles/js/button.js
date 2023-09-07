// This is a "on" replacement to prepend an event to any other of its type
$.fn.prependon = function(evtype, fnc) {
    $(this).each(function() {
        let $this = $(this);
        $this.on(evtype, fnc);
        let events = $._data(this, 'events');
        if (events && events[evtype]) {
            events[evtype].unshift(events[evtype].pop());
        }
    })
}

$("[type=submit]").on("click", function(e) {

    var id = $(this).attr("form") ?? undefined;

    var form = id != undefined ? $("#"+id) : $(this).closest("form");
    if (form.length == 0) form = $("form");

    if (form.length == 1) {

        var submitter = form.find("[type=submit]");
        if(submitter.length == 1) { 
        
            if (submitter[0] != this) 
                submitter.trigger("click");

        } else if(id != undefined) {
            
            form = form[0];
            if ( $(form).hasClass("needs-validation") ) {

                if (!form.checkValidity()) {
    
                    e.preventDefault();
                    e.stopPropagation();
    
                    var invalid = $(form).find(".form-control:invalid");
                    if (invalid.length) {
                        var navPane = $(invalid[0]).closest(".tab-pane");
    
                        var navButton = $("#"+navPane.attr("aria-labelledby"));
                            navButton.one('shown.bs.tab', function() {
                                invalidRequiredField[0].reportValidity();
                            });
    
                        location.hash = navButton.data("bs-target");
                    }
    
                }
    
                var el = $(form).find(":invalid, .has-error");
                if (el.length) {
    
                    // Flag elements as..
                    const style = getComputedStyle(document.body);
                    $([document.documentElement, document.body]).animate(
                        {scrollTop: $(el[0]).offset().top - parseInt(style["scroll-padding-top"])},
                        function () { $(form).addClass('was-validated'); }.bind(form)
                    );
                }
            }
        }
    }
});

$.fn.confirmButton = function(options = {}) {

    // Function that creates a simple modal dialog that contains some placeholders to include custom messages
    function createDialog() {
        let $dialog = $('<div class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">');
        $dialog.append(
            $('<div class="modal-dialog modal-sm modal-dialog-centered" role="document">').append(
                $('<div class="modal-content">').append(
                    $('<div class="modal-body text-center">')
                        .append($('<p class="confirm-text">'))
                ).append(
                    $('<div class="modal-footer">').append(
                        $('<button type="button" class="btn btn-secondary confirm" data-dismiss="modal">').html('Confirm'),
                        $('<button type="button" class="btn btn-secondary cancel" data-dismiss="modal">').html('Cancel')
                    )
                )
            )
        );
        return $dialog;
    }

    this.each(function() {

        if (this._back_onclick === undefined) {
            this._back_onclick = null;
        } else {
            // At this point we are not allowing confirmation chaining
            console.error("ConfirmButton: The element already has a confirmation dialog");
            return;
        }

        // First we'll remove the onclick method, if exists
        if ((this.onclick !== undefined) && (this.onclick !== null)) {
            this._back_onclick = this.onclick;
            this.onclick = null;
        }

        // Now we'll prepend the click event to the element
        $(this).prependon('click', function(e, from = null) {
            let self = $(this);

            if (((this._cjqu_click_payload !== undefined) && (this._cjqu_click_payload !== null)) || (from !== null)) {
                // Clear the payload for next calls
                this._cjqu_click_payload = null;

                // If there was a previous onclick event, we'll execute it
                if (typeof(this._back_onclick) === 'function') {
                    this._back_onclick();
                }
            } else {
                // if ((this._cjqu_click_payload === undefined) || (this._cjqu_click_payload === null)) {
                e.preventDefault();

                // Prevent from executing the other handlers
                e.stopImmediatePropagation();

                // Although the options are for any element, each element found can have its own options
                let defaults = {
                    confirm: null,
                    texttarget: 'p.confirm-text',
                    confirmbtn: 'button.confirm',
                    cancelbtn: 'button.cancel',
                    dialog: null,
                    canceltxt: null,
                    confirmtxt: null
                };
                let settings = $.extend({}, defaults, options);

                // The user can provide a dialogo selector to show, via data-dialog attribute. If it is not found, or it is
                //  not provided, we'll create a new one.
                let dialog;
                if (options.dialog !== undefined) {
                    dialog = options.dialog;
                } else {
                    dialog = self.data('dialog');
                    if (dialog === undefined)
                        dialog = settings.dialog;
                }

                let dialog_created = false;
                let $dialog = $(dialog);
                if ($dialog.length === 0) {
                    $dialog = createDialog();
                    dialog_created = true;
                }

                // If the dialog was created by this function, we know which are the confirm and cancel buttons.
                //   Otherwise, we assume that the dialog was created by the user and he can use the same constructions
                //   (i.e. button.confirm and button.cancel) or provide the selectors via data-cancelbtn and data-confirmbtn
                //   attributes.
                if (dialog_created) {
                    settings.confirmbtn = 'button.confirm';
                    settings.cancelbtn = 'button.cancel';
                } else {
                    if (options.cancelbtn === undefined) {
                        let cancelbtn = self.data('cancelbtn');
                        if (cancelbtn !== undefined) {
                            settings.cancelbtn = cancelbtn;
                        }
                    }
                    if (options.confirmbtn === undefined) {
                        let confirmbtn = self.data('confirmbtn');
                        if (confirmbtn !== undefined) {
                            settings.confirmbtn = confirmbtn;
                        }
                    }
                }

                // Find the confirmation and cancellation buttons
                let $confirmbtn = $dialog.find(settings.confirmbtn);
                let $cancelbtn = $dialog.find(settings.cancelbtn);

                // If we created the dialog, we'll enable to change the confirmation and cancellation texts in buttons, via data-canceltxt and data-confirmtxt attributes
                if (options.canceltxt === undefined) {
                    let canceltxt = self.data('canceltxt');
                    if (canceltxt !== undefined) {
                        settings.canceltxt = canceltxt;
                    }
                }
                if (settings.canceltxt !== null) {
                    $cancelbtn.html(settings.canceltxt);
                }

                if (options.confirmtxt === undefined) {
                    let confirmtxt = self.data('confirmtxt');
                    if (confirmtxt !== undefined) {
                        settings.confirmtxt = confirmtxt;
                    }
                }
                if (settings.confirmtxt !== null) {
                    $confirmbtn.html(settings.confirmtxt);
                }

                // We'll get the text from the button that was clicked, and we'll use it to set the text of the confirmation button, via confirm attribute
                if (options.confirm === undefined) {
                    let text = self.attr("confirm");
                    if (text !== undefined) {
                        settings.confirm = text;
                    }
                }
                if ((settings.confirm !== undefined) && (settings.confirm !== null)) {
                    if (options.texttarget === undefined) {
                        let text_target = self.data("texttarget");
                        if (text_target !== undefined) {
                            settings.texttarget = text_target
                        }
                    }
                    $dialog.find(settings.texttarget).text(settings.confirm);
                }

                // If the dialog was created by this function, we'll add it to the body to be able to use it (we'll dispose it later)
                if (dialog_created) {
                    $dialog.appendTo('body');
                }

                // Now we create a promise that will be resolved when the dialog is closed or any of the buttons is clicked
                let p = new Promise(function(resolve, reject){
                    let confirmed = false;

                    // Handlers for the events (although easy they are separated because we want to be able to remove the handlers)
                    function dialog_hidden(e) {
                        if (confirmed) {
                            resolve();
                        } else {
                            reject();
                        }

                        // Remove the handlers, just in case that the dialog is provided by the user
                        $confirmbtn.off('click', confirm_fnc);
                        $cancelbtn.off('click', cancel_fnc);
                        $dialog.off('hidden.bs.modal', dialog_hidden);

                        // If the dialog was created by this function, we'll dispose it
                        if (dialog_created) {
                            $dialog.remove();
                        }
                    }
                    function confirm_fnc(e){
                        confirmed = true;
                        $dialog.modal('hide');
                    }
                    function cancel_fnc(e) {
                        $dialog.modal('hide');
                    }

                    // We'll resolver or reject the promise after the dialog is closed, so that it offers a better user experience
                    $dialog.on('hidden.bs.modal', dialog_hidden);

                    // If the user clicks on either confirm or cancel button, the dialog is closed to proceed with the promise
                    $confirmbtn.on('click', confirm_fnc);
                    $cancelbtn.on('click', cancel_fnc);

                    // Now show the dialog
                    $dialog.modal('show');

                }).then(function() {
                    // Continue with the action, by simulating the common click action (if it is a clickable element, let's use the
                    //  native click event, otherwise, we'll use the submit event)
                    if (self[0].click !== undefined) {
                        self[0]._cjqu_click_payload = 'from_modal';
                        self[0].click();
                    } else {
                        self.trigger('click', ['from_modal']);
                    }
                }).catch(function() {
                    // User clicked cancel (handled to avoid errors in the console)
                });
            }
        })
    })
}