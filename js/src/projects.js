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
//require("piklor.js");
var Piklor  = require('./piklor.js');
require('../../css/piklor.css');

(function() {
  var colorArray = [
    "#1abc9c"
  , "#2ecc71"
  , "#3498db"
  , "#9b59b6"
  , "#34495e"
  , "#16a085"
  , "#27ae60"
  , "#2980b9"
  , "#8e44ad"
  , "#2c3e50"
  , "#f1c40f"
  , "#e67e22"
  , "#e74c3c"
  , "#ecf0f1"
  , "#95a5a6"
  , "#f39c12"
  , "#d35400"
  , "#c0392b"
  , "#bdc3c7"
  , "#7f8c8d"
  , "#bf678b"
  , "#bf678b"
  , "#c98879"
  , "#ddcb55"
  , "#a5b872"
  , "#6ea68f"
  , "#3794ac"
  , "#0082c9"
  , "#2d73be"
  , "#5b64b3"
  , "#8855a8"
];

  $.ajaxSetup({
    headers: { 'RequestToken': OC.requestToken }
  });
    $( function() {
      var pkedit = null;
        function isAdmin(){
          return oc_isadmin;
        }

        $(document).ready(function() {
            
            $("#dialog-confirm").dialog({
              autoOpen: false,
              modal: true
            });
            var pk = new Piklor.Piklor("#color-picker-project-new", colorArray, {
              open: ".picker-wrapper .btn"
          })
        , wrapperEl = pk.getElm(".picker-wrapper")
        , header = pk.getElm("header")
        , footer = pk.getElm("footer")
        ;
        pk.colorChosen(function (col) {
          wrapperEl.style.backgroundColor = col;
          header.style.backgroundColor = col;
          $('#new-project-color').val(col);
          //footer.style.backgroundColor = col;
      });

        
            // OC.currentUser

            $("#client-select").select2({
              width: '200px',
              placeholder: 'Select client...',
              allowClear: true,
              ajax: { 
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
            }
            });
            
            getProjects();
          });
          $('#show-archived-projects').change(function (e){
            getProjects();
          });
        $("#new-project-submit").click(function (e) {
            e.preventDefault();
            var selectedClient = $('#client-select').select2('data');
            var selectedColor =  $('#new-project-color').val();
            var clientId = null;
            if (selectedClient.length > 0){
              clientId = selectedClient[0].id;
            }
            if ($("#new-project-input").val().trim() == '')
              return false;
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/add-project/'+$("#new-project-input").val());
            var jqxhr = $.post( baseUrl, {clientId:clientId, color:selectedColor} ,function() {
                getProjects();
                $(dialogProjectEditForm).dialog("close");
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
          return false;
        });
        var dialogProjectEditForm = $( "#dialog-project-edit-form" ).dialog({
            autoOpen: false,
            height: 'auto',
            width: 'auto',
            modal: true,
            create: function( event, ui ) {

              var wrapperEl;
              var header;
              var footer;
              pkedit = new Piklor.Piklor("#color-picker-project-edit", colorArray, {
                  open: ".picker-wrapper-project-edit .btn"
                  })
                , wrapperEl = pkedit.getElm(".picker-wrapper-project-edit")
                , header = pkedit.getElm("header")
                , footer = pkedit.getElm("footer")
                ;
                pkedit.colorChosen(function (col) {
                  wrapperEl.style.backgroundColor = col;
                  //header.style.backgroundColor = col;
                  $('#project-edit-color').val(col);
                  //footer.style.backgroundColor = col;
              });

              if (isAdmin()){
                $(".admin-only").removeClass('hidden');
                $('#locked').click(function(){
                   if($('#locked').is(':checked')){
                    $("#locked-options").removeClass('hidden');
                   } else {
                    $("#locked-options").addClass('hidden');
                   }
                  
                });
              } else {
                $(".admin-only").hide();
              }



              $("#locked-select-tags").select2({
                tags: true,
                width: '200px',
                placeholder: "Select tags...",
                allowClear: true,
                ajax: { 
                  url:  OC.generateUrl('/apps/timetracker/ajax/tags'),
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

                  


              $("#locked-select-users").select2({
                tags: true,
                width: '200px',
                placeholder: "Select users...",
                allowClear: true,
                results: function(data) {
                  lastResults = data.results;
                  return {results: data};
                }, 
                ajax: { 
                  headers: {
                    "requesttoken" : oc_requesttoken,
                   
                  },
                  url:  OC.generateUrl('/ocs/v2.php/cloud/users/details?offset=0&search='),
                  formatNoMatches: function() {
                    return '';
                },
                  dataType: 'json',
                  delay: 250,
                  processResults: function (data, page) { //json parse
                      return { 
                         results: $.map(data.ocs.data.users,function(val, i){
                           return { id: i, text:val.displayname};
                         }),
                         pagination: {
                           more: false,
                         }
                      };
                  },
                  cache: false,
                },
              }
              );




              $("#client-select-popup").select2({
                width: '200px',
                placeholder: 'Select client...',
                allowClear: true,
                ajax: { 
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
              $('input.select2-input').attr('autocomplete', "xxxxxxxxxxx")
            },
            buttons: {
              "Delete project": { text:'Delete project',
              click:function(){

                $("#dialog-confirm").dialog({
                  buttons : {
                    "Confirm" : {click: function() {
                          deleteProject(dialogProjectEditForm);
                          $("#dialog-confirm").dialog("close");
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

                 
              }, class:'redButton admin-only'},
              "Edit project": { text:'Edit project',
              click:function(){
                  editProject(dialogProjectEditForm);
              }, class:'primary'},
              Cancel: function() {
                dialogProjectEditForm.dialog( "close" );
              }
            },
            close: function() {
              form[ 0 ].reset();
            }
          });
       
          var form = dialogProjectEditForm.find( "form" ).on( "submit", function( event ) {
            event.preventDefault();
            editProject(dialogProjectEditForm);
          });

        
        function editProject(dialogProjectEditForm){
            var target = dialogProjectEditForm.target;
            var form =  dialogProjectEditForm.find( "form" );
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/edit-project/'+target.getData().id);
            var jqxhr = $.post( baseUrl, {
                                        name:form.find("#name").val(), 
                                        clientId:form.find("#client-select-popup").val(), 
                                        color:form.find("#project-edit-color").val(), 
                                        locked:form.find("#locked").is(':checked')?'1':'0',
                                        archived:form.find("#archived").is(':checked')?'1':'0',
                                        allowedTags:$("#locked-select-tags").val().join(","), 
                                        allowedUsers:$("#locked-select-users").val().join(",") 
                                      },function() {
                getProjects();
                $(dialogProjectEditForm).dialog("close");
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
        function deleteProject(dialogProjectEditForm){
          var target = dialogProjectEditForm.target;
          var form =  dialogProjectEditForm.find( "form" );
          var baseUrl = OC.generateUrl('/apps/timetracker/ajax/delete-project-with-data/'+target.getData().id);
          var jqxhr = $.post( baseUrl, {name:form.find("#name").val(), clientId:form.find("#client-select-popup").val(), locked:form.find("#locked").is(':checked')?'1':'0',archived:form.find("#archived").is(':checked')?'1':'0',  allowedTags:form.find("#locked-select-tags").val(), allowedUsers:form.find("#locked-select-users").val() },function() {
              getProjects();
              $(dialogProjectEditForm).dialog("close");
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
        function getProjects(){
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/projects-table');
         
            var columns = [
              //{title:"Id", field:"id", width:100}, //column has a fixed width of 100px;
              {title:"#", field:"", formatter:"rownum", width: 40, align: "center"},
              {title:"Color", field:"color", formatter:"color", width: 40}, //column will be allocated 1/5 of the remaining space
              {title:"Name", field:"name", widthGrow:1}, //column will be allocated 1/5 of the remaining space
              {title:"Client", field:"client", widthGrow:1}, //column will be allocated 1/5 of the remaining space
              {title:"Locked", field:"locked", widthGrow:1, formatter:"tickCross"}, //column will be allocated 1/5 of the remaining space  
          ];
          var adminColumns = [
            //{title:"Locked", field:"locked", widthGrow:1, formatter:"tickCross"}, //column will be allocated 1/5 of the remaining space
            {title:"Allowed Users", field:"allowedUsers", widthGrow:1}, //column will be allocated 1/5 of the remaining space
            {title:"Allowed Tags", field:"allowedTags", widthGrow:1, mutator: function(value, data, type, params, component){
              data.origAllowedTags = data.allowedTags;
                return data.allowedTags.map(function(d) {
                  return d.name;
                }).join(', '); }}, //column will be allocated 1/5 of the remaining space
          ];
          if (isAdmin()){
            columns = columns.concat(adminColumns);
          }
          if ($("#show-archived-projects").is(':checked')){
            columns = columns.concat([{title:"Archived", field:"archived", widthGrow:1, formatter:"tickCross"}]);
          }

            var table = new Tabulator("#projects", {
              ajaxURL:baseUrl+"?archived="+($("#show-archived-projects").is(':checked')?'1':'0'),
              layout:"fitColumns",
              columns:columns,
              rowClick:function(e, row){
                
                if (!isAdmin() && row.getData().locked){
                  return false;
                }
                e.preventDefault();
                dialogProjectEditForm.target = row;
                
                form = dialogProjectEditForm.find( "form" )
                form.find("#name").val(row.getData().name);
                form.find("#project-edit-color").val(row.getData().color);
                pkedit.set(row.getData().color);
                //form.find("#client-select-popup").val($(e.target).data("client-id")).trigger('change');
                var clientSelectData = {
                  clientId: row.getData().clientId,
                  clientName: row.getData().client,
                };
                
                if (clientSelectData.clientId != null){
                  $('#client-select-popup').append(
                    '<option selected="selected" value="' + clientSelectData.clientId + '">' + clientSelectData.clientName + '</option>'
                  );
                  $("#client-select-popup").trigger('change');
                  }

                form.find("#archived").prop('checked', row.getData().archived);
                if (isAdmin()){
                  var tags = row.getData().origAllowedTags.map(function(e){ return e.id;});
                  var users = row.getData().allowedUsers;
                  
                  $.ajax(OC.generateUrl('/apps/timetracker/ajax/tags'), {
                    dataType: "json"
                  }).done(function(data) { 
                    $('#locked-select-tags').html('');
                    $.each(data.Tags, function( index, value ){
                      if (tags.includes(value.id) ){
                        var option = new Option(value.name, value.id, true, true);
                        $('#locked-select-tags').append(option).trigger('change');

                      }
                    }
                    );
                    $('#locked-select-tags').trigger({
                      type: 'select2:select',
                      params: {
                          data: data
                      }
                  });
                    
                  });

                  $.ajax('/ocs/v2.php/cloud/users/details?offset=0&search=', {
                    dataType: "json",
                    headers: {
                      "requesttoken" : oc_requesttoken,
                     
                    },
                  }).done(function(data) { 

                    var userMap = $.map(data.ocs.data.users,function(val, i){
                      return { id: i, text:val.displayname};
                    });
                    
                    $('#locked-select-users').val(null).trigger('change');
                    $('#locked-select-users').html('');
                    $.each(userMap, function( index, value ){
                      if (users.includes(value.text) ){
                        var option = new Option(value.text, value.id, true, true);
                        $('#locked-select-users').append(option).trigger('change');

                      }
                    }
                    );
                    $('#locked-select-users').trigger({
                      type: 'select2:select',
                      params: {
                          data: data
                      }
                  });
                  });

                  form.find("#locked").prop('checked', row.getData().locked);
                  if($('#locked').is(':checked')){
                    $("#locked-options").removeClass('hidden');
                  } else {
                    $("#locked-options").addClass('hidden');
                  }
                }
                dialogProjectEditForm.dialog("open");
                return false;
              },
              ajaxResponse:function(url, params, response){
        
                return response.items; //return the tableData property of a response json object
            },
            });

        }


      } );
}());
