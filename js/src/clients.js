
var $ = require("jquery");
require("jquery-migrate");
// var moment = require("moment");
require("jqueryui");
//require("jqueryui/jquery-ui.css");
import Tabulator from 'tabulator-tables';
require('tabulator-tables/dist/css/tabulator.css');
require('../../css/style.css');
//require('tabulator-tables/dist/js/jquery_wrapper.js');
// import 'select2/dist/js/select2.full.js'
// require('select2/dist/css/select2.css');
// require('daterangepicker/daterangepicker.css');

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
        $("#new-client-submit").click(function () {
            if ($("#new-client-input").val().trim() == '')
              return false;
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/add-client/'+$("#new-client-input").val());
            var jqxhr = $.post( baseUrl, function() {
                getClients();
                $(dialogClientEditForm).dialog("close");
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
        var dialogClientEditForm = $( "#dialog-client-edit-form" ).dialog({
            autoOpen: false,
            height: 'auto',
            width: 'auto',
            modal: true,
            buttons: {
              "Edit client": {click:function(){
                  editClient(dialogClientEditForm);
                  return false;
              },
              text: 'Edit client',
              class: 'primary'
            },
              Cancel: function() {
                dialogClientEditForm.dialog( "close" );
                return false;
              }
            },
            close: function() {
              form[ 0 ].reset();
            }
          });
       
          var form = dialogClientEditForm.find( "form" ).on( "submit", function( event ) {
            event.preventDefault();
            editClient(dialogClientEditForm);
          });

        getClients();
        function editClient(dialogClientEditForm){
            var target = dialogClientEditForm.target;
            var form =  dialogClientEditForm.find( "form" );
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/edit-client/'+target);
            var jqxhr = $.post( baseUrl, {name:form.find("#name").val()},function() {
                getClients();
                $(dialogClientEditForm).dialog("close");
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

        function getClients(){
          var baseUrl = OC.generateUrl('/apps/timetracker/ajax/clients');

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
                    var baseUrl = OC.generateUrl('/apps/timetracker/ajax/delete-client/'+cell.getRow().getData().id);
                        var jqxhr = $.post( baseUrl, function() {
                            getClients();
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

            dialogClientEditForm.target = cell.getRow().getData().id;
            
            form = dialogClientEditForm.find( "form" )
            form.find("#name").val(cell.getRow().getData().name);
            dialogClientEditForm.dialog("open");

       }},
          ];

          var table = new Tabulator("#clients", {
            ajaxURL:baseUrl,
            layout:"fitColumns",
            columns:columns,
            rowClick:function(e, row){
              return false;
            },
            ajaxResponse:function(url, params, response){
      
              return response.Clients; //return the tableData property of a response json object
          },
          });
        }
      } );
}());
