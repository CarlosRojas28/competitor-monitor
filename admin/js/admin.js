(function () {
	'use strict';

	document.addEventListener('submit', function (event) {
		var form = event.target;

		if (!form.classList || !form.classList.contains('wccm-delete-form')) {
			return;
		}

		var confirmed = window.confirm(wccmAdminConfirmMessage());
		if (!confirmed) {
			event.preventDefault();
		}
	});

	function wccmAdminConfirmMessage() {
		if (window.wccmAdmin && window.wccmAdmin.confirmDelete) {
			return window.wccmAdmin.confirmDelete;
		}

		return '';
	}
})();
