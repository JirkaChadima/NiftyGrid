$(function(){
	$.ajaxSetup({
        success: function(data){
            if(data.redirect){
                $.get(data.redirect);
            }
            if(data.snippets){
                for (var snippet in data.snippets){
                    $("#"+snippet).html(data.snippets[snippet]);
                }
            }
        }
    });
	
	$(".grid-flash-hide").live("click", function() {
		$(this).parent().parent().fadeOut(300);
	});

	$(".grid-select-all").live("click", function(){
		var checkboxes =  $(this).parents("thead").siblings("tbody").children("tr:not(.grid-subgrid-row)").find("td input:checkbox.grid-action-checkbox");
		if($(this).is(":checked")){
			$(checkboxes).attr("checked", "checked");
		}else{
			$(checkboxes).removeAttr("checked");
		}
	});

	$('.grid a.grid-ajax:not(.grid-confirm)').live('click', function (event) {
		event.preventDefault();
		$.get(this.href);
	});

	$('.grid a.grid-confirm:not(.grid-ajax)').live('click', function (event) {
		var $this = $(this);
		if ($this.hasClass('click-disabled')) {
			return null;
		}
		var answer = confirm($(this).data("grid-confirm"));
		return answer;
	});

	$('.grid a.grid-confirm.grid-ajax').live('click', function (event) {
		event.preventDefault();
		var $this = $(this);
		if ($this.hasClass('click-disabled')) {
			return;
		}
		var answer = confirm($this.data("grid-confirm"));
		if(answer){
			$.get(this.href);
		}
	});

	$(".grid-gridForm").find("input[type=submit]").live("click", function(){
		$(this).addClass("grid-gridForm-clickedSubmit");
	});


	$(".grid-gridForm").live("submit", function(event){
		var button = $(".grid-gridForm-clickedSubmit");
		$(button).removeClass("grid-gridForm-clickedSubmit");
		if($(button).data("select")){
			var selectName = $(button).data("select");
			var option = $("select[name=\""+selectName+"\"] option:selected");
			if($(option).data("grid-confirm")){
				var answer = confirm($(option).data("grid-confirm"));
				if(answer){
					if($(option).hasClass("grid-ajax")){
						event.preventDefault();
						$.post(this.action, $(this).serialize()+"&"+$(button).attr("name")+"="+$(button).val());
					}
				}else{
					return false;
				}
			}else{
				if($(option).hasClass("grid-ajax")){
					event.preventDefault();
					$.post(this.action, $(this).serialize()+"&"+$(button).attr("name")+"="+$(button).val());
				}
			}
		}else{
			event.preventDefault();
			$.post(this.action, $(this).serialize()+"&"+$(button).attr("name")+"="+$(button).val());
		}
	});

	$(".grid-autocomplete").live('keydown.autocomplete', function(){
		var gridName = $(this).data("gridname");
		var column = $(this).data("column");
		var link = $(this).data("link");
		$(this).autocomplete({
			source: function(request, response) {
				$.ajax({
					url: link,
					data: gridName+'-term='+request.term+'&'+gridName+'-column='+column,
					dataType: "json",
					method: "post",
					success: function(data) {
						response(data.payload);
					}
				});
			},
			delay: 100,
			open: function() {
				$('.ui-menu').width($(this).width())
				}
		});
	});

	$(".grid-changeperpage").live("change", function(){
		$.get($(this).data("link"), $(this).data("gridname")+"-perPage="+$(this).val());
	});

	function hidePerPageSubmit()
	{
		$(".grid-perpagesubmit").hide();
	}
	hidePerPageSubmit();

	function setDatepicker()
	{
		if (!$.fn.datepicker) return;
		if ($.datepicker) { // TODO pass locale
			$.datepicker.regional['cs'] = {
				closeText: 'Zavřít',
				prevText: '&#x3c;Dříve',
				nextText: 'Později&#x3e;',
				currentText: 'Nyní',
				monthNames: ['leden','únor','březen','duben','květen','červen',
				'červenec','srpen','září','říjen','listopad','prosinec'],
				monthNamesShort: ['led','úno','bře','dub','kvě','čer',
				'čvc','srp','zář','říj','lis','pro'],
				dayNames: ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'],
				dayNamesShort: ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'],
				dayNamesMin: ['ne','po','út','st','čt','pá','so'],
				weekHeader: 'Týd',
				dateFormat: 'yy-mm-dd',
				constrainInput: false,
				firstDay: 1,
				isRTL: false,
				showMonthAfterYear: false,
				yearSuffix: ''
			};
			$.datepicker.setDefaults($.datepicker.regional['cs']);
		}

		$(".grid-datepicker").each(function(){
			if(($(this).val() != "" && $.datepicker)){
				var date = $.datepicker.formatDate('yy-mm-dd', new Date($(this).val()));
			}
			$(this).datepicker();
			$(this).datepicker({
				constrainInput: false
			});
		});
	}
	setDatepicker();
	
	// TODO integrate timepicker

	$(this).ajaxStop(function(){
		setDatepicker();
		hidePerPageSubmit();
	});

	$("input.grid-editable").live("keypress", function(e) {
		if (e.keyCode == '13') {
			e.preventDefault();
			$("input[type=submit].grid-editable").click();
		}
	});

	$("table.grid tbody tr:not(.grid-subgrid-row) td.grid-data-cell").live("dblclick", function(e) {
		$(this).parent().find("a.grid-editable:first").click();
	});
});