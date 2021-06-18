var $ = require("jquery");
require("jquery-migrate");
// var moment = require("moment");
require("jqueryui");
//require("jqueryui/jquery-ui.css");
import Tabulator from 'tabulator-tables';
require('tabulator-tables/dist/css/tabulator.css');
require("daterangepicker");
require('daterangepicker/daterangepicker.css');
import 'select2/dist/js/select2.full.js'
require('select2/dist/css/select2.css');
require('../../css/style.css');

(function() {
  $.ajaxSetup({
    headers: { 'RequestToken': OC.requestToken }
  });

    $( function() {

      var group1 = "project";
      var group2 = "user";
      var group3 = "day";
      var filterProjectId = "";
      var filterClientId = "";


      function timeConverter(UNIX_timestamp){
        var a = new Date(UNIX_timestamp * 1000);
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var year = a.getFullYear();
        var month = months[a.getMonth()];
        var date = a.getDate();
        var hour = a.getHours();
        var min = a.getMinutes();
        var sec = a.getSeconds();
        var time = date + ' ' + month + ' ' + year + ' ' + hour + ':' + min + ':' + sec ;
        return time;
      }

        $(document).ready(function() {
            $("#dialog-confirm").dialog({
              autoOpen: false,
              modal: true
            });

          var start = moment().subtract(29, 'days');
          var end = moment();
          function cb(start, end) {
            $('#report-range span').html(start.format('DD/MM/YY') + ' - ' + end.format('DD/MM/YY'));
          }
          $("#report-range").daterangepicker({
            timePicker: false,
            startDate: start,
            endDate: end,
            ranges: {
              'Today': [moment(), moment()],
              'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
              'Last 7 Days': [moment().subtract(6, 'days'), moment()],
              'Last 30 Days': [moment().subtract(29, 'days'), moment()],
              'Last 90 Days': [moment().subtract(89, 'days'), moment()],
              'Last 365 Days': [moment().subtract(364, 'days'), moment()],
              'This Month': [moment().startOf('month'), moment().endOf('month')],
              'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
              'The Month Before Last': [moment().subtract(2, 'month').startOf('month'), moment().subtract(2, 'month').endOf('month')],
              'This Year': [moment().startOf('year'), moment().endOf('year')],
              'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
              
            },
            locale: {
                format: 'DD/MM/YY',
                firstDay: firstDay
              }
            },cb);
            $("#report-range").on('apply.daterangepicker', function(ev, picker) {
              start = picker.startDate;
              end = picker.endDate;
              getReport();
            });
          cb(start, end);
          $("#group1").select2();
          $("#group2").select2();
          $("#group3").select2();
          $('#group1').on("select2:select select2:unselect", function(e) { 
            
            group1 = (e.params.data.id != null)? e.params.data.id : "";
            getReport();
          });
          $('#group2').on("select2:select select2:unselect", function(e) { 
            group2 = (e.params.data.id != null)? e.params.data.id : "";
            getReport();
          });
          $('#group3').on("select2:select select2:unselect", function(e) { 
            group3 = (e.params.data.id != null)? e.params.data.id : "";
            getReport();
          });
          getReport();
          getTimelines();
          $("#filter-project").select2({
            tags: true,
            width: '200px',
            escapeMarkup : function(markup) { return markup; },
            placeholder: "Select project",
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
            ajax: { 
              tags: true,
                url:  OC.generateUrl('/apps/timetracker/ajax/projects'),
                
                dataType: 'json',
                delay: 250,
                processResults: function (data, page) { //json parse
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

        $("#filter-project").on("change", function (e) { 
         
          filterProjectId = ($(e.target).val() != null)? $(e.target).val() : "";
          
          getReport();
        });



        $("#filter-client").select2({
          tags: true,
          width: '200px',
          escapeMarkup : function(markup) { return markup; },
          placeholder: "Select client",
          allowClear: true,
          ajax: { 
            tags: true,
              url:  OC.generateUrl('/apps/timetracker/ajax/clients'),
              
              dataType: 'json',
              delay: 250,
              processResults: function (data, page) { //json parse
                  return { 
                      results: $.map(data.Clients,function(val, i){
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

      $("#filter-client").on("change", function (e) { 
       
        
        filterClientId = ($(e.target).val() != null)? $(e.target).val() : "";
        getReport();
      });
        $('input.select2-input').attr('autocomplete', "xxxxxxxxxxx");

        
       
          function getReport(){
              var baseUrl = OC.generateUrl('/apps/timetracker/ajax/report?name=&from='+start.unix()+'&to='+end.unix()+'&group1='+group1+'&group2='+group2+'&timegroup='+group3+'&filterProjectId='+filterProjectId+'&filterClientId='+filterClientId);
              function pad(n, width, z) {
                z = z || '0';
                n = n + '';
                return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
              }
              var table = new Tabulator("#report", {
                ajaxURL:baseUrl,
                layout:"fitColumns",
                columns:[
                  //{title:"Id", field:"id", width:100}, //column has a fixed width of 100px;
                  {title:"#", field:"", formatter:"rownum"},
                  {title:"Name", field:"name", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                  {title:"User", field:"userUid", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                  {title:"Project", field:"project", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                  {title:"Client", field:"client", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                  {title:"When", field:"time", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                  {title:"Total Duration", field:"totalDuration",formatter:function(cell, formatterParams, onRendered){
                    //cell - the cell component
                    //formatterParams - parameters set for the column
                    //onRendered - function to call when the formatter has been rendered
                    var duration = cell.getValue();
                    var s = Math.floor( (duration) % 60 );
                    var m = Math.floor( (duration/60) % 60 );
                    var h = Math.floor( (duration/(60*60)));
                    
                    return pad(h,2) + ':' + pad(m,2) + ':' + pad(s,2);
                    
                  },}, //column will be allocated 1/5 of the remaining space
              ],
                ajaxResponse:function(url, params, response){
          
                  return response.items; //return the tableData property of a response json object
              },
              });
              $("#timeline-csv").off().click(function(){
                var baseUrl = OC.generateUrl('/apps/timetracker/ajax/timeline');
                $.post(baseUrl,   // url
                  { 
                    from: start.unix(),
                    to: end.unix(),
                    group1: group1,
                    group2: group2,
                    timegroup: group3,
                    filterProjectId: filterProjectId,
                    filterClientId: filterClientId
                   }, // data to be submit
                  function(data, status, jqXHR) {// success callback
                    getTimelines();
                    });
                return false;
            });
            $("#timeline-csv-email").off().click(function(){
              var baseUrl = OC.generateUrl('/apps/timetracker/ajax/email-timeline');
              $.post(baseUrl,   // url
                { 
                  from: start.unix(),
                  to: end.unix(),
                  group1: group1,
                  group2: group2,
                  timegroup: group3,
                  filterProjectId: filterProjectId,
                  filterClientId: filterClientId
                 }, // data to be submit
                function(data, status, jqXHR) {// success callback
                  getTimelines();
                  });
              return false;
          });
            
          }


          function getTimelines(){
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/timelines');
            function pad(n, width, z) {
              z = z || '0';
              n = n + '';
              return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
            }
            var table = new Tabulator("#timelines", {
              ajaxURL:baseUrl,
              layout:"fitColumns",
              // rowClick:function(e, row){
              // },
              columns:[
                //{title:"Id", field:"id", width:100}, //column has a fixed width of 100px;
                {title:"#", field:"", formatter:"rownum"},
                {title:"Id", field:"id", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                {title:"Status", field:"status", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                //{title:"User", field:"userUid", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                //{title:"Project", field:"projectName", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                //{title:"Client", field:"clientName", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                {title:"When", field:"timeInterval", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                {title:"Total Duration", field:"totalDuration",formatter:function(cell, formatterParams, onRendered){
                  //cell - the cell component
                  //formatterParams - parameters set for the column
                  //onRendered - function to call when the formatter has been rendered
                  var duration = cell.getValue();
                  var s = Math.floor( (duration) % 60 );
                  var m = Math.floor( (duration/60) % 60 );
                  var h = Math.floor( (duration/(60*60)));
                  
                  return pad(h,2) + ':' + pad(m,2) + ':' + pad(s,2);
                  
                },}, //column will be allocated 1/5 of the remaining space
                {title:"created At", field:"createdAt",formatter:function(cell, formatterParams, onRendered){
                  //cell - the cell component
                  //formatterParams - parameters set for the column
                  //onRendered - function to call when the formatter has been rendered
                  var unix = cell.getValue();

                  return timeConverter(unix);
                  

                  
                },}, //column will be allocated 1/5 of the remaining space
                {title:"Download", field:"", formatter:"rownum",formatter:function(cell, formatterParams, onRendered){
                  //cell - the cell component
                  //formatterParams - parameters set for the column
                  //onRendered - function to call when the formatter has been rendered
                  var baseUrl = OC.generateUrl('/apps/timetracker/ajax/download-timeline/'+cell.getRow().getData()["id"]);
                  
                  return '<a href="'+baseUrl+'">'+"Download"+'</a>';
                  
                }},
                {title:"Email", field:"", formatter:"rownum",formatter:function(cell, formatterParams, onRendered){
                  //cell - the cell component
                  //formatterParams - parameters set for the column
                  //onRendered - function to call when the formatter has been rendered
                  
                  
                  return '<a href=# data-id="'+cell.getRow().getData()["id"]+'">Email</a>';
                  
                  },cellClick:function(e, cell){
                    $("#dialog-send-email-form").dialog({
                      buttons : {
                        "Confirm" : {
                          click: function() {
                            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/email-timeline/'+cell.getRow().getData().id);

                            var jqxhr = $.post( baseUrl, {
                                  email:$('#email-address').val(),
                                  subject:$('#email-subject').val(),
                                  content:$('#email-content').val(),
                                }, function() {
                              $("#dialog-send-email-form").dialog("close");
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
                              return false;
                          },
                          text: 'Confirm',
                          class:'primary'
                        },
                        "Cancel" : function() {
                          $(this).dialog("close");
                        }
                      }
                    });
                    $("#dialog-send-email-form").dialog('open');
   
                 //cell.getRow().delete();
                  }
              },
                {formatter:"buttonCross", width:40, align:"center", cellClick:function(e, cell){
                    $("#dialog-confirm").dialog({
                      buttons : {
                        "Confirm" : {
                          click: function() {
                            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/delete-timeline/'+cell.getRow().getData().id);
                            var jqxhr = $.post( baseUrl, function() {
                              getTimelines();
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
                              return false;
                          },
                          text: 'Confirm',
                          class:'primary'
                        },
                        "Cancel" : function() {
                          $(this).dialog("close");
                        }
                      }
                    });
                    $("#dialog-confirm").dialog('open');
   
                 //cell.getRow().delete();
                  }
                },
               
            ],
              ajaxResponse:function(url, params, response){
        
                return response.Timelines; //return the tableData property of a response json object
            },
            });
         
        }
      });


      } );
}());
