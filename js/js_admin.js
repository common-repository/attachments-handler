function forceAnalysisAH() {
	jQuery('#forceAnalysisAH').attr('disabled', 'disabled');
	jQuery('#stopAnalysisAH').removeAttr('disabled');
	jQuery('#wait_analysisAH').show() ;
	var arguments = {
		action: 'forceAnalysisAttachments'
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		if ((""+response+ "").indexOf("PROGRESS - ") !=-1) {
			if (jQuery('#forceAnalysisAH').is(":disabled")) {
				jQuery('#table_formatting').html(response) ;
				forceAnalysisAH() ; 
			}
		} else {
			jQuery('#forceAnalysisAH').removeAttr('disabled');
			jQuery('#stopAnalysisAH').attr('disabled', 'disabled');
			jQuery('#table_formatting').html(response) ;
			jQuery('#wait_analysisAH').hide() ;
		}
	});
}

function stopAnalysisAH() {
	jQuery('#forceAnalysisAH').removeAttr('disabled');
	jQuery('#stopAnalysisAH').attr('disabled', 'disabled');
	jQuery('#wait_analysisAH').hide() ;
	
	var arguments = {
		action: 'stopAnalysisAttachments'
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		// nothing
	});
}

function cleanAnalysisAH() {
	
	var r=confirm("Sure to reset all entries?");
	if (r==true) {
		jQuery('#wait_analysisAH').show() ;

		var arguments = {
			action: 'cleanAnalysisAttachments'
		} 
		jQuery.post(ajaxurl, arguments, function(response) {
			window.location.href=window.location.href ; 
		});
	}
}

function forceRegenerateAH() {
	jQuery('#forceRegenerateAH').attr('disabled', 'disabled');
	jQuery('#stopRegenerateAH').removeAttr('disabled');
	jQuery('#wait_regenerateAH').show() ;
	var arguments = {
		action: 'forceRegenerateAttachments'
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		if ((""+response+ "").indexOf("PROGRESS - ") !=-1) {
			if (jQuery('#forceRegenerateAH').is(":disabled")) {
				jQuery('#table_regenerate').html(response) ;
				forceRegenerateAH() ; 
			}
		} else {
			jQuery('#forceRegenerateAH').removeAttr('disabled');
			jQuery('#stopRegenerateAH').attr('disabled', 'disabled');
			jQuery('#table_regenerate').html(response) ;
			jQuery('#wait_regenerateAH').hide() ;
		}
	});
}

function stopRegenerateAH() {
	jQuery('#forceRegenerateAH').removeAttr('disabled');
	jQuery('#stopRegenerateAH').attr('disabled', 'disabled');
	jQuery('#wait_regenerateAH').hide() ;
	
	var arguments = {
		action: 'stopRegenerateAttachments'
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		// nothing
	});
}

function doNotignoreAttachmentIssue(id_entry, sha1_entry, msg) {
	var res = confirm(msg) ; 
	if (res==true) {
		var arguments = {
			action: 'doNotignoreAttachmentIssue' ,
			id: id_entry,
			sha1: sha1_entry
		} 
		  
		//POST the data and append the results to the results div
		jQuery.post(ajaxurl, arguments, function(response) {
			if (response=="ok") {
				jQuery("#ligne"+id_entry).hide();
				window.location.href=window.location.href ; 
			} else {
				jQuery("#ligne"+id_entry).append(" "); // Just to stop the waiting image
				alert(response) ; 			
			}
		}).error(function(x,e) { 
			alert("Error "+x.status) ; 
			jQuery("#ligne"+id_entry).append(" ");// Just to stop the waiting image
		});		
	} else {
		jQuery("#ligne"+id_entry).append(" ");// Just to stop the waiting image
	}
}

function ignoreAttachmentIssue(id_entry, sha1_entry, msg) {
	var res = confirm(msg) ; 
	if (res==true) {
		var arguments = {
			action: 'ignoreAttachmentIssue' ,
			id: id_entry,
			sha1: sha1_entry
		} 
		  
		//POST the data and append the results to the results div
		jQuery.post(ajaxurl, arguments, function(response) {
			if (response=="ok") {
				jQuery("#ligne"+id_entry).hide();
				window.location.href=window.location.href ; 
			} else {
				jQuery("#ligne"+id_entry).append(" "); // Just to stop the waiting image
				alert(response) ; 			
			}
		}).error(function(x,e) { 
			alert("Error "+x.status) ; 
			jQuery("#ligne"+id_entry).append(" ");// Just to stop the waiting image
		});		
	} else {
		jQuery("#ligne"+id_entry).append(" ");// Just to stop the waiting image
	}
}