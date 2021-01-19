var $ = require("jquery");
require("jquery-migrate");
require("daterangepicker");
var moment = require("moment");
require("jqueryui");
//require("jqueryui/jquery-ui.css");
//import 'select2/dist/js/select2.full.js'
import 'select2/dist/js/select2.js'
require('select2/dist/css/select2.css');
require('daterangepicker/daterangepicker.css');
require('../../css/style.css');


(
  

function() {
  
    $.ajaxSetup({
      headers: { 'RequestToken': OC.requestToken }
    });
  /*select2($);*/



    $( function() {
        var days='30';
        var start = moment().startOf('day').subtract(29, 'days');
        var end = moment().endOf('day');

        function cb(start, end) {
            $('#report-range span').html(start.format('DD/MM/YY') + ' - ' + end.format('DD/MM/YY'));
        }
        $("#report-range").daterangepicker({
            timePicker: false,
            startDate: start,
            endDate: end,
            showCustomRangeLabel: false,
            ranges: {
                'Today': [moment().startOf('day'), moment().endOf('day')],
                'Last 7 Days': [moment().startOf('day').subtract(6, 'days'), moment().endOf('day')],
                'Last 30 Days': [moment().startOf('day').subtract(29, 'days'), moment().endOf('day')],
                'Last 90 Days': [moment().startOf('day').subtract(89, 'days'), moment().endOf('day')],
                'Last 365 Days': [moment().startOf('day').subtract(364, 'days'), moment().endOf('day')],
                'This Month': [moment().startOf('month'), moment().endOf('day')],
                'This Year': [moment().startOf('year'), moment().endOf('day')],
                'Starting last year': [moment().startOf('year').subtract(1, 'year'), moment().endOf('day')],
                'Last 3 years': [moment().startOf('day').subtract(3, 'year'), moment().endOf('day')],
                'Last 5 years': [moment().startOf('day').subtract(5, 'year'), moment().endOf('day')],
            },
            locale: {
                format: 'DD/MM/YY'
            }
          },cb);
          $("#report-range").on('apply.daterangepicker', function(ev, picker) {
            days = Math.round((picker.endDate.unix()-picker.startDate.unix()) / 86400);
            getWorkItems();
          });
      cb(start, end);

      var entityMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '/': '&#x2F;',
        '`': '&#x60;',
        '=': '&#x3D;'
      };
      
      function escapeHtml (string) {
        return String(string).replace(/[&<>"'`=\/]/g, function (s) {
          return entityMap[s];
        });
      }

        getWorkItems();
        var timerInterval;
        var dialogWorkItemEditForm = $( "#dialog-work-item-edit-form" ).dialog({
            autoOpen: false,
            height: 400,
            width: 350,
            modal: true,
            buttons: {
              "Edit work item": function(){
                 editWorkIntem(dialogWorkItemEditForm);
              },
              Cancel: function() {
                dialogWorkItemEditForm.dialog( "close" );
              }
            },
            close: function() {
              var form =  dialogWorkItemEditForm.find( "form" );
              form[ 0 ].reset();
            }
          });
          var picker = $("#hours-manual-entry").daterangepicker({
            timePicker: true,
            //startDate:tsToDate($(this).data('start-date')),
            //endDate:tsToDate($(this).data('end-date')),
            timePicker24Hour: true,
            locale: {
                format: 'DD/MM/YY hh:mm:ss'
              }
        });
        function validateManualEntryFields(){
            if($('#name-manual-entry').val() == ''){
                $("#confirm-button").button("option", "disabled", true);
            } else {
                $("#confirm-button").button("option", "disabled", false);
            }
        }
        $('#name-manual-entry').on('input',function() {

            validateManualEntryFields();
        });
        
        $("#dialog-manual-entry").dialog({
            autoOpen: false,
            buttons : 
              [ {
                  id: 'confirm-button',
                  text: "Confirm",
                  click: function() {
                      var baseUrl = OC.generateUrl('/apps/timetracker/ajax/add-work-interval/'+encodeURIComponent(encodeURIComponent($('#name-manual-entry').val()))); // encode twice so we can have slashes
                    
                      var jqxhr = $.post( baseUrl,
                            {
                              start:picker.data('daterangepicker').startDate.format('DD/MM/YY HH:mm'), 
                              end:picker.data('daterangepicker').endDate.format('DD/MM/YY HH:mm'), 
                              tzoffset: new Date().getTimezoneOffset(),
                              async: true,
                              details:$('#details-manual-entry').val()} ,function() {
                                getWorkItems();
                                $("#dialog-manual-entry").dialog("close");
                              })
                      .done(function(data, status, jqXHR) {
                          var response = data;
                          if ('Error' in response){
                            alert(response.Error);
                          }
                        })
                      .fail(function() {
                          alert( "error" );
                      })
                  },
                },
                {
                  id: 'cancel-button',
                  text: "Cancel",
                  click: function() {
                    $(this).dialog("close");
                  },
                },
              ]

          });

          $('#manual-entry-button').click(function(e) {
            $("#hours-manual-entry").val('');
            $("#dialog-manual-entry").dialog("open");
            return false;
        });
        validateManualEntryFields();

        function editWorkIntem(dialogWorkItemEditForm){
          var target = dialogWorkItemEditForm.target;
          var form =  dialogWorkItemEditForm.find( "form" );
          var id = $(target).data('myid');
          var baseUrl = OC.generateUrl("/apps/timetracker/ajax/update-work-interval/"+id);
          var jqxhr = $.post( baseUrl, {name:form.find("#name").val(),details:form.find("#details").val()},function() {
              getWorkItems();
              $(dialogWorkItemEditForm).dialog("close");
            })
            .done(function(data, status, jqXHR) {
              var response = data;
              if ('Error' in response){
                alert(response.Error);
              }
            })
            .fail(function() {
              alert( "error" );
            })
            .always(function() {
              
            });

      }
        function cutString(s, n){
            if (s.length < n) {
                return s;
            }
            return s.substring(0, n - 4) + ' ...';
        }
        function secondsToTimer(s){
            function pad(num, size) {
                var s = num+"";
                while (s.length < size) s = "0" + s;
                return s;
            }
            var secondsInDay = 60*60*24;
            var secondsInHour = 60*60;
            var secondsInMinute = 60;

            var days = Math.floor(s/secondsInDay);
            var hours = Math.floor((s-days*secondsInDay)/secondsInHour);
            var minutes = Math.floor((s-days*secondsInDay-hours*secondsInHour)/secondsInMinute);
            var seconds = s % 60;
            if (days > 0){
                return days + "&nbsp;days&nbsp;"+pad(hours,2)+':'+pad(minutes,2)+':'+pad(seconds,2);
            } else {
                return pad(hours,2)+':'+pad(minutes,2)+':'+pad(seconds,2);
            }
        }
        function tsToDate(ts){
            var date = new Date(ts*1000);
            // Hours part from the timestamp
            var hours = date.getHours();
            // Minutes part from the timestamp
            var minutes = "0" + date.getMinutes();
            // Seconds part from the timestamp
            var seconds = "0" + date.getSeconds();
            var year = date.getFullYear() % 100;
            //var year = date.getYear();
            var month = date.getMonth()+1;
            var day = date.getDate();
            // Will display time in 10:30:23 format
            var formattedTime = day+"/"+month+"/"+year+" "+hours + ':' + minutes.substr(-2) + ':' + seconds.substr(-2);
            return formattedTime;
        }
        function tsToHour(ts){
            var date = new Date(ts*1000);
            // Hours part from the timestamp
            var hours = date.getHours();
            // Minutes part from the timestamp
            var minutes = "0" + date.getMinutes();
            // Seconds part from the timestamp
            var seconds = "0" + date.getSeconds();

            // Will display time in 10:30:23 format
            var formattedTime = hours + ':' + minutes.substr(-2) + ':' + seconds.substr(-2);
            return formattedTime;
        }
        function getWorkItems() {
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/work-intervals?days='+days);
            $.ajaxSetup({
              scriptCharset: "utf-8",
              //contentType: "application/json; charset=utf-8"
          });
            $.getJSON( baseUrl, function( data ) {
                
                if (data.running.length > 0){
                    localStorage.setItem('isTimerStarted', true);
                    localStorage.setItem('timerStartTime', data.running[0].start);
                    var now = Math.floor(Date.now() / 1000);
                    localStorage.setItem('timerStartTimeLocal', data.running[0].start + now - data.now );
                    $('#start-tracking > span').addClass("stop-button").removeClass("play-button");
                    timerInterval = setInterval(function() {
                        if (localStorage.getItem('isTimerStarted') === 'false'){
                          clearInterval(timerInterval);
                          return;
                        }
                        var timerStartTimeLocal = localStorage.getItem('timerStartTimeLocal');
                        var now = Math.floor(Date.now() / 1000);

                        $('#timer').html(secondsToTimer(now - timerStartTimeLocal));
                    }, 1000);
                } else {
                    clearInterval(timerInterval);
                    localStorage.setItem('isTimerStarted', false);
                    $('#timer').html(secondsToTimer(0));
                    $('#start-tracking > span').addClass("play-button").removeClass("stop-button");
                }
                

                var days = [];
                $.each( data.days, function( dayName, dayMap ) {
                  
                    var dayItems = [];
                    $.each(dayMap, function (dayItemName, workItem){
                        var children = [];
                        
                        $.each(workItem.children, function (ckey, child){
                            children.push(
                              "<li>"+
                                "<div class='wi-child'>"+
                                  "<div class='wi-child-element'>"+
                                    "<div class='wi-child-name clickable'  data-details='"+escapeHtml(child.details)+"' data-myid="+child.id+" data-name='"+escapeHtml(child.name)+"'>"+escapeHtml(cutString(child.name,64))+
                                      "<div class='wi-child-details clickable' data-details='"+escapeHtml(child.details)+"' data-myid="+child.id+" data-name='"+escapeHtml(child.name)+"'>"+escapeHtml(cutString(child.details,64))+"</div>"+
                                    "</div>"+
                                    "<span class='fas clickable fa-trash wi-trash' id="+child.id+"></span>"+
                                    "<select class='set-project' data-myid="+child.id+" data-projectid="+child.projectId+" data-projectname='"+escapeHtml(child.projectName)+"' data-projectcolor='"+escapeHtml(child.projectColor)+"'>"+
                                      ((child.projectName == null)?"":("<option selected='selected' value='"+child.projectId+"' text='"+escapeHtml(child.projectName)+"' >"+escapeHtml(child.projectName)+"</option>"))+
                                    "</select>"+
                                    "<select class='set-tag' multiple=\"multiple\" data-myid="+child.id+" data-tagids='"+child.tags.map(function(tag) {return tag.id}).join(',')+"' data-tagnames='"+child.tags.map(function(tag) {return tag.name}).join(',')+"'>"+
                                      child.tags.map(function(tag) {return "<option selected='selected' value='"+tag.id+"' text='"+tag.name+"' >"+tag.name+"</option>";}).join(' ')+
                                    "</select>"+
                                    "<div class='wi-child-hours' data-myid="+child.id+" data-start-date='"+child.start+"' data-end-date='"+(child.start+child.duration)+"'>"+
                                      tsToHour(child.start)+"&nbsp;-&nbsp;"+((child.running == 1)?'':tsToHour(child.start+child.duration))+
                                    "</div>"+
                                    "<div class='wi-child-duration'>"+((child.running == 1)?'running...':secondsToTimer(child.duration))+"</div>"+
                                    "<div class='wi-play-space'>"+
                                      "<span class='fas clickable fa-play wi-play' id="+child.id+" data-work-name='"+child.name+"' data-projectid="+child.projectId+" data-tagids='"+child.tags.map(function(tag) {return tag.id}).join(',')+"' ></span>"+
                                    "</div>"+
                                  "</div>"+
                                "</div>"+
                              "</li>");
                        });


                        dayItems.push("<div class='work-item'>"+
                                        "<ul>"+
                                          "<li class=''>"+
                                            "<div class='work-item-element'>"+
                                              ((children.length == 1)?"<div class='wi-len-empty'>&nbsp;</div>":"<div class='wi-len'>"+children.length+"</div>")+
                                              "<div class='wi-name'>"+
                                                cutString(dayItemName,128)+
                                              "</div>"+
                                              "<div class='wi-duration'>"+secondsToTimer(workItem.totalTime)+
                                              "</div>"+
                                            "</div>"+
                                          "</li>"+
                                          children.join("")+
                                        "</ul>"+
                                      "</div>");
                    });
                    days.push(  "<div class='day-work-intervals'>"+
                                  "<ul>"+
                                    "<li class='day-list-item'>" +
                                      "<div class='day-name'>" + dayName +"</div>"+ 
                                      dayItems.join("") + 
                                    "</li>"+
                                  "</ul>"+
                                "</div>" );
                });

                  $("#work-intervals").html($( "<div/>", {
                      "class": "my-new-list",
                      html: days.join( "" )
                    }));


                $(".wi-child-hours").each(function(){
                    $(this).daterangepicker({
                        timePicker: true,
                        startDate:tsToDate($(this).data('start-date')),
                        endDate:tsToDate($(this).data('end-date')),
                        timePicker24Hour: true,
                        locale: {
                            format: 'DD/MM/YY hh:mm:ss'
                          }
                    });

                    $(this).on('apply.daterangepicker', function(ev, picker) {
                        var id = $(this).data('myid');
                        var jqxhr = $.post( "ajax/update-work-interval/"+id,{start:picker.startDate.format('DD/MM/YY HH:mm'), end:picker.endDate.format('DD/MM/YY HH:mm'), tzoffset: new Date().getTimezoneOffset()}, function() {
                        })
                         .done(function(data, status, jqXHR) {
                            var response = data;
                            if ('Error' in response){
                              alert(response.Error);
                            }
                            getWorkItems();
                          })
                         .fail(function() {
                           alert( "error" );
                         })
                         .always(function() {
                         });
                    });
                });
                $('.wi-child-name').click(function(e) {
                    e.preventDefault();
                    dialogWorkItemEditForm.target = e.target;
                    
                    var form = dialogWorkItemEditForm.find( "form" )
                    form.find("#name").val($(e.target).data("name"));
                    form.find("#details").val($(e.target).data("details"));
                    dialogWorkItemEditForm.dialog("open");
                    return false;

                })
                $('.wi-play').click(function(e) {
                    e.preventDefault();
                    $('#work-input').val($(this).data('work-name'));
                    startTimer($(this).data('projectid'), $(this).data('tagids'));
                    return false;
                })
                $('.wi-trash').click(function(e) {
                    $("#dialog-confirm").dialog({
                        buttons : {
                          "Confirm" : function() {
                            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/delete-work-interval/'+e.target.id);
                                var jqxhr = $.post( baseUrl, function() {
                                    getWorkItems();
                                    $("#dialog-confirm").dialog("close");
                                })
                                    .done(function(data, status, jqXHR) {
                                        var response = data;
                                        if ('Error' in response){
                                          alert(response.Error);
                                        }
                                    })
                                    .fail(function() {
                                        alert( "error" );
                                    })
                          },
                          "Cancel" : function() {
                            $(this).dialog("close");
                          }
                        }
                      });
                    $("#dialog-confirm").dialog("open");
                    return false;
                });

                $(".set-project").each(function(){
                    var id = $(this).data('myid');
                    var projectId = $(this).data('projectid');
                   
                    var projectName = $(this).data('projectname');
                    var projectColor = $(this).data('projectcolor');
                                        
                    
                    $(this).select2({
                        width: '200px',
                        escapeMarkup : function(markup) { return markup; },
                        placeholder: "<span class='fas fa-folder'></span>",
                        allowClear: true,
                        templateResult: function formatState (project) {
                          var color = '#ffffff';
                          if (project.color) {
                            color = project.color;
                          }
                          var $state = $(
                            '<span class="select-project"><span class="select-project-color" style="background-color:'+color+';" ></span>' + project.text + '</span>'
                          );
                          return $state;
                        },
                        templateSelection: function formatState (project) {
                          var color = '#ffffff';
                          if (projectColor) {
                            color = projectColor;
                          }
                          var $state = $(
                            '<span class="select-project"><span class="select-project-color" style="background-color:'+color+';" ></span>' + project.text + '</span>'
                          );
                          return $state;
                        },
                        ajax: { 
                            url:  OC.generateUrl('/apps/timetracker/ajax/projects'),
                            
                            dataType: 'json',
                            delay: 250,
                            processResults: function (data) { //json parse
                                return { 
                                    results: $.map(data.Projects,function(val, i){
                                        return { id: val.id, text:val.name, color: val.color};
                                        }),
                                    pagination: {
                                    more: false,
                                    }
                                };
                            },
                            cache: false,
                            
                        },
                    });
                    $(this).data('myid',id);
                    
                    $(this).val(projectId).trigger('change');

                });
                $(".set-tag").each(function(){
                    var id = $(this).data('myid');
                    var tagIds = [];
                    var tagNames = [];
                    if(typeof($(this).data('tagids')) !== 'undefined'){
                        tagIds = $(this).data('tagids').toString().split(',');
                        tagNames = $(this).data('tagnames').toString().split(',');
                    }

                  $(this).select2({
                    tags: true,
                    width: '200px',
                    placeholder: "Select tags...",
                    allowClear: true,
        
                    ajax: { 
                        url:  function () { return OC.generateUrl('/apps/timetracker/ajax/tags')+'?workItem='+$(this).data('myid');},
                        data: function (params){
                          var query = {
                            q: params.term,
                            type: 'public'
                          }
                    
                          // Query parameters will be ?search=[term]&type=public
                          return query;
                        },
                        headers: { 'RequestToken': OC.requestToken },
                        formatNoMatches: function() {
                            return '';
                        },
                        dataType: 'json',
                        delay: 250,
                        processResults: function (data) { //json parse
                          return { 
                             results: $.map(data.Tags,function(val, i){
                               return { id: val.id, text:val.name};
                             }),
                             pagination: {
                               more: false,
                             }
                          };
                        },
                        cache: false,
                    },
                  });
                  $(this).select2('val', []);
                });
                  $('input.select2-input').attr('autocomplete', "xxxxxxxxxxx")

                  //$(".set-project").on("change", function (e) {
                  $(".set-project").on("select2:select select2:unselect", function (e) {
                        var myid = $(e.target).data('myid');
                        var selectedId = $(e.target).val();
                        var jqxhr = $.post( "ajax/update-work-interval/"+myid,{projectId:selectedId}, function() {
                            
                            
                           })
                           .done(function(data, status, jqXHR) {
                                var response = data;
                                if ('Error' in response){
                                  alert(response.Error);
                                }
                                getWorkItems();
                             })
                            .fail(function() {
                              alert( "error" );
                            })
                            .always(function() {
                            });
                    });
                    $(".set-project").on("select2:unselecting", function(e) {
                        var self = $(this);
                        setTimeout(function() {
                          self.select2('close');
                      }, 0);
                    });

                    $(".set-tag").on("change", function (e) { 
                        var myid = $(e.target).data('myid');
                        var selectedTag = $(e.target).val();
                        var jqxhr = $.post( "ajax/update-work-interval/"+myid,{tagId:selectedTag.join(",")}, function() {
                           })
                           .done(function(data, status, jqXHR) {
                                var response = data;
                                if ('Error' in response){
                                  alert(response.Error);
                                }
                                getWorkItems();
                            })
                            .fail(function() {
                              alert( "error" );
                            })
                            .always(function() {
                            });
                    });
                    $(".set-tag").on("select2:unselecting", function(e) {
                      var self = $(this);
                      setTimeout(function() {
                        self.select2('close');
                    }, 0);
                  });
              }).fail(function() {
                alert( "error getting work items" );
              });
        }

        function startTimer(projectId = null, tags = ""){
            if(localStorage.getItem('isTimerStarted') === 'true'){
                stopTimer(startTimer, [projectId, tags]);
                return;
            }
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/start-timer');
            var workName = $('#work-input').val();
            if (workName == ''){
                workName = 'no description';
            }
            var jqxhr = $.post( "ajax/start-timer/"+encodeURIComponent(encodeURIComponent(workName)), { projectId: projectId, tags: tags}, function() {
               })
               .done(function(data, status, jqXHR) {
                var response = data;
                if ('Error' in response){
                  alert(response.Error);
                } else {
                  localStorage.setItem('isTimerStarted', true);
                  $('#start-tracking > span').addClass("stop-button").removeClass("play-button");
                }
              })
               .fail(function() {
                  alert( "error" );
               }).always(function() {
                getWorkItems();
              });
            
        }
        function stopTimer(onStopped = null, args = []){
            
            var workName = $('#work-input').val();
            if (workName == ''){
                workName = 'no description';
            }
            var jqxhr = $.post( "ajax/stop-timer/"+encodeURIComponent(encodeURIComponent(workName)), function() { // encode twice so we can pass / character
               })
               .done(function(data, status, jqXHR) {
                var response = data;
                if ('Error' in response){
                  alert(response.Error);
                } else {
                  localStorage.setItem('isTimerStarted', false);
                  $('#start-tracking > span').addClass("play-button").removeClass("stop-button");
                  if (onStopped != null){
                    onStopped(args[0], args[1]);
                  } else {
                    getWorkItems();
                  }
                }
              })
                .fail(function() {
                  alert( "error" );
                })
                .always(function() {
                  
                });
        }



        $( "#datepicker-from" ).datepicker();
        $( "#datepicker-to" ).datepicker();
        
        if(localStorage.getItem('isTimerStarted') === 'true'){
            $('#start-tracking > span').addClass("stop-button").removeClass("play-button");
        } else {
            $('#start-tracking > span').addClass("play-button").removeClass("stop-button");
        }
        $( "#start-tracking" ).click(function() {
            if(localStorage.getItem('isTimerStarted') === 'true'){
                stopTimer();
            } else {
                startTimer();
            }
            return false;
          });
      } );
      
}());
