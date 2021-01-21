var $ = require("jquery");
require("jquery-migrate");
// var moment = require("moment");
require("jqueryui");
//require("jqueryui/jquery-ui.css");
import Tabulator from 'tabulator-tables';
require('tabulator-tables/dist/css/tabulator.css');

import 'select2/dist/js/select2.full.js'
require('select2/dist/css/select2.css');

require('../../css/style.css');
(function() {
  $.ajaxSetup({
    headers: { 'RequestToken': OC.requestToken }
  });

    $( function() {
        var newGoalProjectId;
        $(document).ready(function() {
            $("#dialog-confirm").dialog({
              autoOpen: false,
              modal: true
            });
        });


        $("#project-select").select2({
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
        $('#project-select').on("select2:select select2:unselect", function(e) { 
          newGoalProjectId = ($(e.target).val() != null)? $(e.target).val() : "";
        });

          
        $("#new-goal-submit").click(function () {
          if ($("#new-goal-hours").val().trim() == '')
              return false;
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/add-goal');
            var jqxhr = $.post( baseUrl, {
                  projectId : newGoalProjectId,
                  hours: $("#new-goal-hours").val(),
                  interval: $("#new-goal-interval").val(),
              },  function() {

                getGoals();
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
        });
        
        getGoals();
        function getGoals(){
          var baseUrl = OC.generateUrl('/apps/timetracker/ajax/goals');

          var editIcon = function(cell, formatterParams){ //plain text value
            return "<i class='fa fa-edit'></i>";
        };
        
        
          var columns = [
            {title:"#", field:"", formatter:"rownum", width: 40, align: "center"},
            {title:"Project", field:"projectName", widthGrow:1}, //column will be allocated 1/5 of the remaining space
            {title:"Target Hours", field:"hours", widthGrow:1}, //column will be allocated 1/5 of the remaining space
            {title:"Interval", field:"interval", widthGrow:1}, //column will be allocated 1/5 of the remaining space
            {title:"Started At", field:"createdAt", widthGrow:1, mutator: function(value, data, type, params, component){
                              return new Date(data.createdAt*1000).toDateString();}
                            }, //column will be allocated 1/5 of the remaining space
            {title:"Hours spent current interval", field:"workedHoursCurrentPeriod", widthGrow:1}, //column will be allocated 1/5 of the remaining space
            {title:"Past Debt in Hours", field:"debtHours", widthGrow:1}, //column will be allocated 1/5 of the remaining space
            {title:"Remaining Hours", field:"remainingHours", widthGrow:1}, //column will be allocated 1/5 of the remaining space
            {title:"Total Remaining Hours", field:"totalRemainingHours", widthGrow:1}, //column will be allocated 1/5 of the remaining space
            {formatter:"buttonCross", width:40, align:"center", cellClick:function(e, cell) {
               $("#dialog-confirm").dialog({
                buttons : {
                  "Confirm" : {click: function() {
                    var baseUrl = OC.generateUrl('/apps/timetracker/ajax/delete-goal/'+cell.getRow().getData().id);
                        var jqxhr = $.post( baseUrl, function() {
                          getGoals();
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
          }},
          
          ];

          var table = new Tabulator("#goals", {
            ajaxURL:baseUrl,
            layout:"fitColumns",
            columns:columns,
            rowClick:function(e, row){
              return false;
            },
            ajaxResponse:function(url, params, response){
      
              return response.Goals; //return the tableData property of a response json object
            },
          });
        }
      } );
}());
