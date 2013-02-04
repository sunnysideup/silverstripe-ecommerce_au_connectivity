jQuery(document).ready(
	function() {
		AUPostDeliverModifier.init();
	}
);


(function($){
	$(document).ready(
		function() {
			AUPostDeliverModifier.init();
		}
	);
})(jQuery);

var AUPostDeliverModifier = {

	formSelector: "#AUPostDeliverModifier_Form_AUPostDeliverModifier",

	errorMessage: "Please check your postal code",

	actionsSelector: ".Actions",

	loadingClass: "loading",

	isRunning: false,

	lastPostalCode: 0,

	lastCountry: "",

	init: function() {
		this.setupForm();
		this.setupFields();
	},

	// setup form
	setupForm: function (formData, jqForm, options) {
		var options = {
			beforeSubmit: AUPostDeliverModifier.showRequest,  // pre-submit callback
			success: AUPostDeliverModifier.showResponse,  // post-submit callback
			dataType: "json"
		};
		jQuery(AUPostDeliverModifier.formSelector).ajaxForm(options);
		jQuery(AUPostDeliverModifier.formSelector + " " + AUPostDeliverModifier.actionsSelector).hide();
		jQuery(AUPostDeliverModifier.formSelector+ " input[name=IsPickUp]").change(
			function() {
				if(!AUPostDeliverModifier.isRunning) {
					AUPostDeliverModifier.isRunning = true;
					jQuery(AUPostDeliverModifier.formSelector).submit();
					isChecked = jQuery(this).is(':checked');
					if(isChecked) {
						jQuery(AUPostDeliverModifier.formSelector+ " #PostalCodeForDelivery").slideUp();
					}
					else {
						jQuery(AUPostDeliverModifier.formSelector+ " #PostalCodeForDelivery").slideDown();
					}
				}
				AUPostDeliverModifier.isRunning = false;
			}
		);
		if(jQuery(AUPostDeliverModifier.formSelector+ " input[name=IsPickUp]").is(':checked')) {
			jQuery(AUPostDeliverModifier.formSelector+ " #PostalCodeForDelivery").slideUp();
		}
		else {
			jQuery(AUPostDeliverModifier.formSelector+ " #PostalCodeForDelivery").slideDown();
		}
	},

	// pre-submit form callback
	showRequest: function (formData, jqForm, options) {
		jQuery(AUPostDeliverModifier.formSelector).addClass(AUPostDeliverModifier.loadingClass);
		return true;
	},

	// post-submit form callback
	showResponse: function (responseText, statusText)  {
		jQuery(AUPostDeliverModifier.formSelector).removeClass(AUPostDeliverModifier.loadingClass);
		EcomCart.setChanges(responseText);
		AUPostDeliverModifier.isRunning = false;
	},

	// setup fields
	setupFields: function() {
		jQuery('input[name=Country],input[name=ShippingCountry],input[name=PostalCode],input[name=ShippingPostalCode],input[name=UseShippingAddress],input[name=PostalCodeForDelivery]').change(
			function() {
				AUPostDeliverModifier.updatePostalCodeAndCountry(this);
			}
		);
		jQuery('input[name=PostalCodeForDelivery]').keyup(
			function() {
				var postalCodeCheck = parseInt(jQuery(this).val());
				if(postalCodeCheck > 1000 && postalCodeCheck < 10000) {
					AUPostDeliverModifier.updatePostalCodeAndCountry(this);
				}
			}
		);
	},

	// what happens when a field is updated...
	updatePostalCodeAndCountry: function (inputField) {
		if(!AUPostDeliverModifier.isRunning) {
			var value = "";
			var useShippingAddress = jQuery('[name=UseShippingAddress]').is(':checked');
			var myPostalCode = "";
			var myCountry = "";
			if(jQuery(inputField).attr("name") == "PostalCodeForDelivery") {
				myPostalCode = jQuery(inputField).val();
				if(useShippingAddress) {
					jQuery("input[name=ShippingPostalCode]").val(myPostalCode);
				}
				else {
					jQuery("input[name=PostalCode]").val(myPostalCode);
				}
			}
			if(jQuery(inputField).attr("name") == "PostalCode") {
				myPostalCode = jQuery(inputField).val();
				jQuery("input[name=PostalCodeForDelivery]").val(myPostalCode);
			}
			else if(jQuery(inputField).attr("name") == "ShippingPostalCode" && useShippingAddress) {
				myPostalCode = jQuery(inputField).val();
				jQuery("input[name=PostalCodeForDelivery]").val(myPostalCode);
			}
			if(jQuery(useShippingAddress)) {
				myCountry = jQuery('[name=ShippingCountry]').val();
			}
			else {
				myCountry = jQuery('[name=Country]').val();
			}
			var url = jQuery('base').attr('href') + 'aupostdelivermodifier/amount';
			var postalCodeCheck = parseInt(myPostalCode);
			if(postalCodeCheck > 100 && postalCodeCheck < 10000) {
				if(AUPostDeliverModifier.lastPostal != myPostalCode || AUPostDeliverModifier.lastCountry != myCountry) {
					AUPostDeliverModifier.lastPostal = myPostalCode;
					AUPostDeliverModifier.lastCountry = myCountry;
					jQuery(AUPostDeliverModifier.formSelector).addClass(AUPostDeliverModifier.loadingClass);
					jQuery.ajax({
						type : 'POST',
						url : url,
						data : {postcode : myPostalCode, country: myCountry},
						dataType : 'json',
						success : function(responseText) {
							jQuery(AUPostDeliverModifier.formSelector).removeClass(AUPostDeliverModifier.loadingClass);
							EcomCart.setChanges(responseText);
							AUPostDeliverModifier.isRunning = false;
						}
					});
				}
				else {
					AUPostDeliverModifier.isRunning = false;
				}
			}
			else {
				AUPostDeliverModifier.isRunning = false;
				jQuery(AUPostDeliverModifier.formSelector).removeClass(AUPostDeliverModifier.loadingClass);
				alert(AUPostDeliverModifier.errorMessage);
			}
		}
	}

}
