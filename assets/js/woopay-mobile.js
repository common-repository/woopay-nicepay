var testmode = document.getElementById( 'testmode' ).value;
var checkoutURL = document.getElementById( 'checkout_url' ).value;
var payForm = document.getElementById( 'order_info' );

function nicepay() {
	document.charset = 'euc-kr';
	payForm.method = 'post';
	payForm.action = 'https://web.nicepay.co.kr/smart/interfaceURL.jsp';
	document.charset = 'utf-8';
	payForm.submit();
}

function startNicePay() {
	if ( payForm.PayMethod.value == 'CARD' || payForm.PayMethod.value == 'VBANK' ) {
		if ( payForm.Amt.value <= 500 ) {
			alert( woopay_string.method_msg );
			returnToCheckout();
		} else if ( payForm.BuyerName.value.length < 2 ) {
			alert( woopay_string.name_msg );
			returnToCheckout();
		} else {
			nicepay();
		}
	} else {
		nicepay();
	}
}

function returnToCheckout() {
	payForm.action = checkoutURL;
	payForm.submit();
}

function startWooPay() {
	if ( testmode ) {
		if ( ! confirm( woopay_string.testmode_msg ) ) {
			returnToCheckout();
		} else {
			startNicePay();
		}
	} else {
		startNicePay();
	}
}

jQuery( document ).ready(function() {
	setTimeout( 'startWooPay();', 500 );
});