var controllerurl = location.pathname.replace(/edit$/, '').replace(/edit\/$/, '');

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
		
		// preview stuff
		
		if($('.markitup').length > 0){
			var t;
			var previewdiv = $('#editorPreview'); 
			previewdiv.hide();
			previewdiv.after('<div id="showPreview"><a href="#">show / hide preview</a></div>');
			updatePreview(false);
			
			$('#showPreview a').click(function(){
				previewdiv.toggle();
				return false;
			});
			
			$('#Form_EditForm_Content').focus(function(){
				setPreviewTimer();		
			}).blur(function(){
				clearTimeout(t);
			});
		}
		
		function setPreviewTimer(){
			t = setTimeout(updatePreview,5000);	
		}

		function updatePreview(repeat){
			repeat = typeof(repeat) != 'undefined' ? repeat : true;
			$.post(
				previewdiv.attr('data-url'), 
				{ content: $('#Form_EditForm_Content').val() },
				function(data){
					if(data) 
						previewdiv.html(data);	
					else 
						previewdiv.html('error: no data');
				}
			);
			if(repeat) setPreviewTimer();
		}
		
		// Link and image dialogs
		
		
		window.simpleWikiImageDialog = function(){
		
		}
		
		window.simpleWikiLinkDialog = function(){
	
			$("#dialogContent").load(controllerurl + '/linkpicker').dialog({
		        title: "Insert a link",
				modal: true,
				autoOpen: true,
				height: 500,
				width: 500,
				buttons: {
					"Insert Link": function() {
						if($('#Type input:radio:checked').val() == 'external'){
							link = $('#Form_LinkPickerForm_Link').val();
						}else{
							link = $('#Form_LinkPickerForm_Link').attr('data-link');
						}
						title = '"' + $('#Form_LinkPickerForm_Title').val() + '"';
						$.markItUp({openWith:'[', closeWith:'](' + link + ' ' + title + ')' } );
						$( this ).dialog( "close" );
						$('#Form_EditForm_Content').focus();
					},
					"Cancel": function() {
						$( this ).dialog( "close" );
						$('#Form_EditForm_Content').focus();
					}	
				}
			});
		}
			
		$( "#Form_LinkPickerForm_Link" ).livequery(function(){
			if($('#Type input:radio:checked').val() != 'external'){
				$(this).autocomplete({
					source: function( request, response ) {
						$.get(controllerurl + '/linklist', {term : request.term, type : $('#Type input:radio:checked').val()}, function(data){
							if(data && data.length){
								var items = [];
								for (var id = 0; id < data.length; id++){
									items.push({
										label : data[id].Label,
										value : data[id].Title,
										id : data[id].ID,
										link : data[id].Link
									});
								}
								response(items);
							}
						});
					},
					minLength: 2,
					select: function( event, ui ) {
						$(this).val(ui.item.label);
						$(this).attr('data-id', ui.item.id);
						$(this).attr('data-link', ui.item.link);
					}
				});
			}
		});


	});
})(jQuery);




		
		