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
	
	$(document).on("click", ".grid-flash-hide", function() {
		$(this).parent().parent().fadeOut(300);
	});
	
	$(document).on("click", ".grid-select-all", function(){
		var checkboxes =  $(this).parents("thead").siblings("tbody").children("tr:not(.grid-subgrid-row)").find("td input:checkbox.grid-action-checkbox");
		if($(this).is(":checked")){
			$(checkboxes).attr("checked", "checked");
		}else{
			$(checkboxes).removeAttr("checked");
		}
	});

	$(document).on("click", ".grid a.grid-ajax:not(.grid-confirm)", function (event) {
		event.preventDefault();
		$.get(this.href);
	});

	$(document).on("click", ".grid a.grid-confirm:not(.grid-ajax)", function (event) {
		var $this = $(this);
		if ($this.hasClass('click-disabled')) {
			return null;
		}
		var answer = confirm($(this).data("grid-confirm"));
		return answer;
	});

	$(document).on('click', '.grid a.grid-confirm.grid-ajax', function (event) {
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

	$(document).on("click", ".grid-gridForm input[type=submit]", function(){
		$(this).addClass("grid-gridForm-clickedSubmit");
	});


	$(document).on("submit", ".grid-gridForm", function(event){
		var button = $(".grid-gridForm-clickedSubmit");
		$(button).removeClass("grid-gridForm-clickedSubmit");
		
		var off = [];
		$('input[type=checkbox]:not(:checked)', this).each(function () {
			var $this = $(this);
			off.push($this.attr('name') + '=off');
		});
		
		if($(button).data("select")){
			var selectName = $(button).data("select");
			var option = $("select[name=\""+selectName+"\"] option:selected");
			if($(option).data("grid-confirm")){
				var answer = confirm($(option).data("grid-confirm"));
				if(answer){
					if($(option).hasClass("grid-ajax")){
						event.preventDefault();
						$.post(this.action, $(this).serialize()+"&"+off.join('&')+"&"+$(button).attr("name")+"="+$(button).val());
					}
				}else{
					return false;
				}
			}else{
				if($(option).hasClass("grid-ajax")){
					event.preventDefault();
					$.post(this.action, $(this).serialize()+"&"+off.join('&')+"&"+$(button).attr("name")+"="+$(button).val());
				}
			}
		}else{
			event.preventDefault();
			$.post(this.action, $(this).serialize()+"&"+off.join('&')+"&"+$(button).attr("name")+"="+$(button).val());
		}
	});

	$(document).on("keydown.autocomplete", ".grid-autocomplete", function(){
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

	$(document).on("change", ".grid-changeperpage", function(){
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

	$(document).on("keypress", "input.grid-editable", function(e) {
		if (e.keyCode === 13) {
			e.preventDefault();
			$("input[type=submit].grid-editable").click();
		}
	});

	$(document).on("dblclick", "table.grid tbody tr:not(.grid-subgrid-row) td.grid-data-cell", function(e) {
		$(this).parent().find("a.grid-editable:first").click();
	});
});