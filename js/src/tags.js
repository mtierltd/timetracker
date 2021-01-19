var $ = require("jquery");
require("jquery-migrate");
// var moment = require("moment");
require("jqueryui");
//require("jqueryui/jquery-ui.css");
import Tabulator from 'tabulator-tables';
require('tabulator-tables/dist/css/tabulator.css');
require('../../css/style.css');
(function() {
  $.ajaxSetup({
    headers: { 'RequestToken': OC.requestToken }
  });

    $( function() {

        $(document).ready(function() {
            $("#dialog-confirm").dialog({
              autoOpen: false,
              modal: true
            });
          });
          
        $("#new-tag-submit").click(function () {
          if ($("#new-tag-input").val().trim() == '')
              return false;
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/add-tag/'+$("#new-tag-input").val());
            var jqxhr = $.post( baseUrl, function() {

                getTags();
                $(dialogTagEditForm).dialog("close");
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
        var dialogTagEditForm = $( "#dialog-tag-edit-form" ).dialog({
            autoOpen: false,
            height: 400,
            width: 350,
            modal: true,
            buttons: {
              "Edit tag": {click: function(){
                  editTag(dialogTagEditForm);
                  return false;
              },
              text: 'Edit tag',
              class:'primary'
            },
              Cancel: function() {
                dialogTagEditForm.dialog( "close" );
              }
            },
            close: function() {
              form[ 0 ].reset();
            }
          });
       
          var form = dialogTagEditForm.find( "form" ).on( "submit", function( event ) {
            event.preventDefault();
            editTag(dialogTagEditForm);
          });

        getTags();
        function editTag(dialogTagEditForm){
            var target = dialogTagEditForm.target;
            var form =  dialogTagEditForm.find( "form" );
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/edit-tag/'+target);
            var jqxhr = $.post( baseUrl, {name:form.find("#name").val()},function() {
                getTags();
                $(dialogTagEditForm).dialog("close");
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

        }
        function getTags(){
          var baseUrl = OC.generateUrl('/apps/timetracker/ajax/tags');

          var editIcon = function(cell, formatterParams){ //plain text value
            return "<i class='fa fa-edit'></i>";
        };
        
        
          var columns = [
            {title:"#", field:"", formatter:"rownum", width: 40, align: "center"},
            {title:"Name", field:"name", widthGrow:1}, //column will be allocated 1/5 of the remaining space
            {formatter:"buttonCross", width:40, align:"center", cellClick:function(e, cell){
               $("#dialog-confirm").dialog({
                buttons : {
                  "Confirm" : {click: function() {
                    var baseUrl = OC.generateUrl('/apps/timetracker/ajax/delete-tag/'+cell.getRow().getData().id);
                        var jqxhr = $.post( baseUrl, function() {
                            getTags();
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
          {formatter:editIcon, width:40, align:"center", cellClick:function(e, cell){

            dialogTagEditForm.target = cell.getRow().getData().id;
            
            form = dialogTagEditForm.find( "form" )
            form.find("#name").val(cell.getRow().getData().name);
            dialogTagEditForm.dialog("open");

       }},
          ];

          var table = new Tabulator("#tags", {
            ajaxURL:baseUrl,
            layout:"fitColumns",
            columns:columns,
            rowClick:function(e, row){
              return false;
            },
            ajaxResponse:function(url, params, response){
      
              return response.Tags; //return the tableData property of a response json object
          },
          });
        }
      } );
}());
