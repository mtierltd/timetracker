

(function() {


    $( function() {

        getWorkItems();
        var timerInterval;
        dialogWorkItemEditForm = $( "#dialog-work-item-edit-form" ).dialog({
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
                    var baseUrl = OC.generateUrl('/apps/timetracker/ajax/add-work-interval/'+$('#name-manual-entry').val());

                    var jqxhr = $.post( baseUrl,{start:picker.data('daterangepicker').startDate.format('DD/MM/YY HH:mm'), end:picker.data('daterangepicker').endDate.format('DD/MM/YY HH:mm'), tzoffset: new Date().getTimezoneOffset()} ,function() {
                        getWorkItems();
                        $("#dialog-manual-entry").dialog("close");
                    })
                    .done(function() {
                    
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
              },]
          });

          $('#manual-entry-button').click(function(e) {
            $("#dialog-manual-entry").dialog("open");
            return false;
        });
        validateManualEntryFields();

          function editWorkIntem(dialogWorkItemEditForm){
            target = dialogWorkItemEditForm.target;
            form =  dialogWorkItemEditForm.find( "form" );
            var id = $(target).data('myid');
            var baseUrl = OC.generateUrl("/apps/timetracker/ajax/update-work-interval/"+id);
            var jqxhr = $.post( baseUrl, {name:form.find("#name").val()},function() {
                getWorkItems();
                $(dialogWorkItemEditForm).dialog("close");
              })
                .done(function() {
                  
                })
                .fail(function() {
                  alert( "error" );
                })
                .always(function() {
                  
                });

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
        function getWorkItems(){
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/work-intervals');
            $.getJSON( baseUrl, function( data ) {
                
                if (data.running.length > 0){
                    localStorage.setItem('isTimerStarted', true);
                    localStorage.setItem('timerStartTime', data.running[0].start);
                    var now = Math.floor(Date.now() / 1000);
                    localStorage.setItem('timerStartTimeLocal', data.running[0].start + now - data.now );
                    $('#start-tracking > span').addClass("stop-button").removeClass("play-button");
                    timerInterval = setInterval(function() {
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
                            //debugger;
                            children.push("<div class='wi-child'><li><div class='wi-child-element'><div class='wi-child-name clickable'  data-myid="+child.id+" data-name='"+child.name+"'>"+child.name+"</div>"+
                            "<span class='fas clickable fa-trash wi-trash' id="+child.id+"></span><span class='set-project' data-myid="+child.id+" data-projectid="+child.projectId+" data-projectname='"+child.projectName+"'></span>"+
                            "<span class='set-tag' data-myid="+child.id+" data-tagids='"+child.tags.map(function(tag) {return tag.id}).join(',')+"' data-tagnames='"+child.tags.map(function(tag) {return tag.name}).join(',')+"'></span>"+
                            "<div class='wi-child-hours' data-myid="+child.id+" data-start-date='"+child.start+"' data-end-date='"+(child.start+child.duration)+"'>"+tsToHour(child.start)+"&nbsp;-&nbsp;"+
                            ((child.running == 1)?'':tsToHour(child.start+child.duration))+
                                    "</div>"+
                            "<div class='wi-child-duration'>"+((child.running == 1)?'running...':secondsToTimer(child.duration))+"</div>"+
                            "<div class='wi-play-space'><span class='fas clickable fa-play wi-play' id="+child.id+" data-work-name='"+child.name+"'></span><div>"+"</div></li></div>");
                        });


                        dayItems.push("<div class='work-item'>"+"<ul><li class=''><div class='work-item-element'>"+
                                    ((children.length == 1)?"<div class='wi-len-empty'>&nbsp;</div>":"<div class='wi-len'>"+children.length+"</div>")+
                                    "<div class='wi-name'>"+
                                    dayItemName+"</div><div class='wi-duration'>"+secondsToTimer(workItem.totalTime)+
                                    "</div>"+
                                    "</div></li>"+children.join("")+"</ul>"+"</div>");
                    });
                    days.push( "<div class='day-work-intervals'><ul><li class='day-list-item'>" +
                        "<div class='day-name'>" + dayName +"</div>"+ dayItems.join("") + "</li></ul></div>" );
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
                         .done(function() {
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
                    
                    form = dialogWorkItemEditForm.find( "form" )
                    form.find("#name").val($(e.target).data("name"));
                    dialogWorkItemEditForm.dialog("open");
                    return false;

                })
                $('.wi-play').click(function(e) {
                    e.preventDefault();
                    $('#work-input').val($(this).data('work-name'));
                    startTimer();
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
                                    .done(function() {
                                    
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
                                        
                    
                    $(this).select2({
                        width: '200px',
                        escapeMarkup : function(markup) { return markup; },
                        placeholder: "<span class='fas fa-folder'></span>",
                        allowClear: true,
                        ajax: { 
                            url:  OC.generateUrl('/apps/timetracker/ajax/projects'),
                            
                            dataType: 'json',
                            delay: 250,
                            results: function (data, page) { //json parse
                                return { 
                                    results: $.map(data.Projects,function(val, i){
                                        return { id: val.id, text:val.name};
                                        }),
                                    pagination: {
                                    more: false,
                                    }
                                };
                            },
                            cache: false,
                            
                        },
                        initSelection: function(element, callback) {
                            var results;
                                results = [];
                                results.push({
                                    id: projectId,
                                    text: projectName,
                                    });
                                
                                callback(results[0]);
                        }
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
                        url:  OC.generateUrl('/apps/timetracker/ajax/tags')+'?workItem='+$(this).data('myid'),
                        formatNoMatches: function() {
                            return '';
                        },
                        dataType: 'json',
                        delay: 250,
                        results: function (data, page) { //json parse
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
                    initSelection: function(element, callback) {
                        var results;
                            results = [];
                            for(var x=0;x<tagIds.length; x++){

                                if (tagIds[x] == ''){
                                    continue;
                                }
                                results.push({
                                    id: tagIds[x],
                                    text: tagNames[x],
                                });
                            }
                            callback(results);
                    }
                  });
                  $(this).select2('val', []);
                });
                  $('input.select2-input').attr('autocomplete', "xxxxxxxxxxx")

                  $(".set-project").on("change", function (e) { 
                        var myid = $(e.target).data('myid');
                        var selectedId = $(e.target).val();
                        var jqxhr = $.post( "ajax/update-work-interval/"+myid,{projectId:selectedId}, function() {
                            
                            
                           })
                            .done(function() {
                                getWorkItems();
                            })
                            .fail(function() {
                              alert( "error" );
                            })
                            .always(function() {
                            });
                    });

                    $(".set-tag").on("change", function (e) { 
                        var myid = $(e.target).data('myid');
                        var selectedTag = $(e.target).val();
                        var jqxhr = $.post( "ajax/update-work-interval/"+myid,{tagId:selectedTag}, function() {
                           })
                            .done(function() {
                                getWorkItems();
                            })
                            .fail(function() {
                              alert( "error" );
                            })
                            .always(function() {
                            });
                    });
              }).fail(function() {
                alert( "error getting work items" );
              });
        }

        function startTimer(){
            if(localStorage.getItem('isTimerStarted') === 'true'){
                stopTimer();
            }
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/start-timer');
            var workName = $('#work-input').val();
            if (workName == ''){
                workName = 'no description';
            }
            var jqxhr = $.post( "ajax/start-timer/"+workName, function() {
                localStorage.setItem('isTimerStarted', true);
                $('#start-tracking > span').addClass("stop-button").removeClass("play-button");
                getWorkItems();
               })
                .done(function() {
                })
                .fail(function() {
                  alert( "error" );
                })
            
        }
        function stopTimer(){
            
            var workName = $('#work-input').val();
            if (workName == ''){
                workName = 'no description';
            }
            var jqxhr = $.post( "ajax/stop-timer/"+workName, function() {
                localStorage.setItem('isTimerStarted', false);
                $('#start-tracking > span').addClass("play-button").removeClass("stop-button");
                getWorkItems();
               })
                .done(function() {
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