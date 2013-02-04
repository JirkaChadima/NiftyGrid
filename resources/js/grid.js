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
		$(this).typeahead({
			source: function (query, process) {
				$.ajax({
					url: link,
					data: gridName+'-term='+query+'&'+gridName+'-column='+column,
					dataType: 'json',
					method: 'post',
					success: function (data) {
						return process(data.payload);
					}
				});
			},
			items: 15,
			minLength: 2
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

	function setDatetimepicker()
	{
		if (!$.fn.datetimepicker) return;

		$(".grid-datetimepicker").each(function(){
			$(this).datetimepicker({
				keyboardNavigation: false,
				startDate: '1900-01-01'
			});
		});
	}
	setDatetimepicker();

	function repositionGlobalButtons() {
		$(".grid-global-buttons").each(function () {
				var $this = $(this);
				$this.css({
					marginLeft: 0 - ($this.outerWidth() / 2)
				});
			});
	}
	repositionGlobalButtons();

	$(this).ajaxStop(function(){
		setDatetimepicker();
		hidePerPageSubmit();
		repositionGlobalButtons();
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