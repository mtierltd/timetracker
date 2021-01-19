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

    $( function() {

      var group1 = "project";
      var group2 = "user";
      var group3 = "day";
      var filterProjectId = "";
      var filterClientId = "";


        $(document).ready(function() {
            $("#dialog-confirm").dialog({
              autoOpen: false,
              modal: true
            });



            function editTimeline(dialogTimelineEditForm){
                var target = dialogTimelineEditForm.target;
                var form =  dialogTimelineEditForm.find( "form" );
                var baseUrl = OC.generateUrl('/apps/timetracker/ajax/edit-timeline/'+target.getData().id);
                var jqxhr = $.post( baseUrl, {status:form.find("#status").val()},function() {
                    getTimelines();
                    $(dialogTimelineEditForm).dialog("close");
                })
                    .done(function(data, status, jqXHR) {
                    var response = data;
                    if ('Error' in response){
                        alert(response.Error);
                    }
                    })
                    .fail(function() {
                    alert( "error" );
                    });

            }

            var dialogTimelineEditForm = $( "#dialog-timeline-edit-form" ).dialog({
                autoOpen: false,
                height: 400,
                width: 350,
                modal: true,
                create: function( event, ui ) {
                    
                },
                buttons: {
                    "Edit timeline": { text:'Edit timeline',
                    click:function(){
                        editTimeline(dialogTimelineEditForm);
                    }, class:'primary'},
                    Cancel: function() {
                        dialogTimelineEditForm.dialog( "close" );
                    }
                  },
                  close: function() {
                    
                    
                  }
            });

          
          
          getTimelines();

          function getTimelines(){

            var editIcon = function(cell, formatterParams){ //plain text value
                return "<i class='fa fa-edit'></i>";
            };
            function pad(n, width, z) {
                z = z || '0';
                n = n + '';
                return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
              }

            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/timelines-admin');
            var table = new Tabulator("#timelines", {
              ajaxURL:baseUrl,
              layout:"fitColumns",
            //    rowClick:function(e, row){
            //     e.preventDefault();
            //     dialogTimelineEditForm.target = row;
            //     debugger;
            //     dialogTimelineEditForm.find('#status').val(row.getData().status);
            //     dialogTimelineEditForm.dialog("open");
            //     return false;
            //    },
              columns:[
                //{title:"Id", field:"id", width:100}, //column has a fixed width of 100px;
                {title:"#", field:"", formatter:"rownum"},
                {title:"Id", field:"id", widthGrow:1}, //column will be allocated 1/5 of the remaining space
                {title:"User", field:"userUid", widthGrow:1}, //column will be allocated 1/5 of the remaining space
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

                  {formatter:editIcon, width:40, align:"center", cellClick:function(e, cell){

                    dialogTimelineEditForm.target = cell.getRow();
                    dialogTimelineEditForm.find('#status').val(cell.getRow().getData().status);
                    dialogTimelineEditForm.dialog("open");
                    return false;
        
               }},
               
            ],
              ajaxResponse:function(url, params, response){
        
                return response.Timelines; //return the tableData property of a response json object
            },
            });
         
        }
      });


      } );
}());