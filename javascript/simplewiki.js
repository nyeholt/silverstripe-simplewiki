
(function ($) {
	$().ready(function () {
		// find the save button and add in its accesskey... really need to
		// get around to patching this in core!
		$('#Form_StatusForm_action_startediting').attr('accesskey', 's');

		// add the lock update ping
		var timeout = $('#Form_EditForm_LockLength').val() ? $('#Form_EditForm_LockLength').val() * 1000 : 300000;

		if (timeout) {
			setInterval(function () {
				var url = $('#Form_EditForm_LockUpdate').val();
				$.post(url);
			}, timeout);
		}

		$('#Form_EditForm_LockUpdate')

		// toggle the new page form
		var createPageForm = $('#Form_CreatePageForm').hide();
		$('#Form_EditForm_action_addpage_t').click(function () {
			createPageForm.toggle();
		    return false;
		});

		// deleting an existing page
		$('#Form_EditForm_action_delete').click(function () {
			if (!confirm("Are you sure you want to delete this page?")) {
				return false;
			}
			return true;
		});

		// manage the creation of a new page
		createPageForm.submit(function () {
			if (!$(this).find('[@name="NewPageName"]').val()) {
				$(this).find('[@name="NewPageName"]').css('border', '1px solid red');
				return false;
			}
		});


	});
})(jQuery);