		var swfu;
			var settings = {
				flash_url : json.global.coreWebroot+"/public/js/swfupload/swfupload_fp10/swfupload.swf",
				flash9_url :json.global.coreWebroot+"/public/js/swfupload/swfupload_fp9/swfupload_fp9.swf",
				upload_url:json.global.webroot+"/upload/saveuploaded",
				post_params: {"sid" : $('.sessionId').val(),"privacy": $('.privacyUpload').val() },
				file_size_limit : $('.maxSizeFile').val()+" MB",
				file_types : "*.*",
				file_types_description : "All Files",
				file_upload_limit : 100,
				file_queue_limit : 0,
				custom_settings : {
					progressTarget : "fsUploadProgress",
					cancelButtonId : "btnCancel"
				},
				debug: false,

				// Button settings
				button_image_url: json.global.coreWebroot+"/public/js/swfupload/images/Button_65x29.png",
				button_width: "65",
				button_height: "20",
				button_placeholder_id: "spanButtonPlaceHolder",
				button_text: '<span class="theFont">'+$('.buttonBrowse').val()+'</span>',
				button_text_style: ".theFont { font-size: 12; }",
				button_text_left_padding: 5,
				button_text_top_padding: 0,
				
				// The event handler functions are defined in handlers.js
				swfupload_preload_handler : preLoad,
				swfupload_load_failed_handler : loadFailed,
				file_queued_handler : fileQueued,
				file_queue_error_handler : fileQueueError,
				file_dialog_complete_handler : fileDialogComplete,
				upload_start_handler : uploadPreStart,
				upload_progress_handler : uploadProgress,
				upload_error_handler : uploadError,
				upload_success_handler : uploadSuccess,
				upload_complete_handler : uploadComplete,
				queue_complete_handler : queueComplete	// Queue plugin event
			};

			swfu = new SWFUpload(settings);
      
      function uploadPreStart(file)
      {
        swfu.setPostParams({"sid" : $('.sessionId').val(),"privacy": $('.privacyUpload').val() });
        //uploadStart(file);
      }
      
      
		$( "#uploadTabs" ).tabs({
			ajaxOptions: {
        beforeSend: function()
        {
          $('div.MainDialogLoading').show();
        },
        success: function()
        {
          $('div.MainDialogLoading').hide();
          $( "#uploadTabs" ).show();
        },
				error: function( xhr, status, index, anchor ) {
					$( anchor.hash ).html(
						"Couldn't load this tab. We'll try to fix this as soon as possible. " );
				}
			}
		});
    $( "#uploadTabs" ).show();
      $('#linkForm').ajaxForm(function() { 
         // $('input[name=url]').val('http://');
          $('.uploadedLinks').val(parseInt($('.uploadedLinks').val())+1);
          updateUploadedCount();
      });
      
      
    function updateUploadedCount()
    {
      var count=parseInt($('.uploadedSimple').val())+parseInt($('.uploadedLinks').val())+parseInt($('.uploadedJava').val());
      $('.globalUploadedCount').html(count);
      if(count>0)
        {
        $('.reviewUploaded').show();
        }
      else
        {
        $('.reviewUploaded').hide();
        }
    }