

(function() {


    $( function() {

        function isAdmin(){
          return oc_isadmin;
        }

        $(document).ready(function() {
            $("#dialog-confirm").dialog({
              autoOpen: false,
              modal: true
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
                results: function (data, page) { //json parse
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
            var clientId = null;
            if (selectedClient !== null){
              clientId = selectedClient.id;
            }
            if ($("#new-project-input").val().trim() == '')
              return false;
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/add-project/'+$("#new-project-input").val());
            var jqxhr = $.post( baseUrl, {clientId:clientId} ,function() {
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
        dialogProjectEditForm = $( "#dialog-project-edit-form" ).dialog({
            autoOpen: false,
            height: 400,
            width: 350,
            modal: true,
            create: function( event, ui ) {
              //debugger;
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

                  var arr = $(element).val().split(',').map(function (x){
                      return parseInt(x);
                  });
                  $.ajax(OC.generateUrl('/apps/timetracker/ajax/tags'), {
                    dataType: "json"
                  }).done(function(data) { 
                    $.each(data.Tags, function( index, value ){
                      if (arr.includes(value.id) ){
                        results.push({
                          id: value.id,
                          text: value.name,
                          });
    
                      }
                    });
                    callback(results);
                });
                  
                }
              });


              $("#locked-select-users").select2({
                tags: true,
                width: '200px',
                placeholder: "Select users...",
                allowClear: true,
                ajax: { 
                  headers: {
                    "requesttoken" : oc_requesttoken,
                   
                  },
                  url:  '/ocs/v2.php/cloud/users/details?offset=0&search=',
                  formatNoMatches: function() {
                    return '';
                },
                  dataType: 'json',
                  delay: 250,
                  results: function (data, page) { //json parse
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
                initSelection: function(element, callback) {

                  var results;
                  results = [];

                  var arr = $(element).val().split(',');
                  $.ajax('/ocs/v2.php/cloud/users/details?offset=0&search=', {
                    dataType: "json"
                  }).done(function(data) { 

                    $.map(data.ocs.data.users,function(val, i){
                      if ( arr.includes(i) ){
                        results.push({
                          id: i,
                          text: val.displayname,
                          });
                        }
                      return;
                    });

                   
                   
                    callback(results);
                });
                }
              });




              $("#client-select-popup").select2({
                width: '200px',
                placeholder: 'Select client...',
                allowClear: true,
                ajax: { 
                  url:  OC.generateUrl('/apps/timetracker/ajax/clients'),
                  
                  dataType: 'json',
                  delay: 250,
                  results: function (data, page) { //json parse
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
              initSelection: function(element, callback) {
                var results;
                var jsn = JSON.parse($(element).val());
                
                results = [];
                if (jsn.clientId != "undefined"){
                  results.push({
                    id: jsn.clientId,
                    text: jsn.clientName,
                    });
                }
                
                callback(results[0]);

              }
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
       
          form = dialogProjectEditForm.find( "form" ).on( "submit", function( event ) {
            event.preventDefault();
            editProject(dialogProjectEditForm);
          });

        
        function editProject(dialogProjectEditForm){
            target = dialogProjectEditForm.target;
            form =  dialogProjectEditForm.find( "form" );
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/edit-project/'+target.getData().id);
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
        function deleteProject(dialogProjectEditForm){
          target = dialogProjectEditForm.target;
          form =  dialogProjectEditForm.find( "form" );
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
            // var table = window.kingtable = new KingTable({
            //   url: baseUrl,
            //   lruCacheSize: 0,
            //   element:  document.getElementById("projects"),
            //   //caption: "KingTable - colors demo with server side pagination (paginated set in memory)",
            //   id: "projects-table",
            //   itemCount:false,
            //   columns: {
            //     name: {
            //       displayName:"Name",
            //       html: function (item){
            //         //debugger;
            //          return "<a href='#'><div class='edit-name' id='"+item.id+"' data-name='"+item.name+"' data-client-id='"+item.clientId+"' data-client-name='"+item.client+"'>"+item.name+"</div></a>";
            //     },
            //     },
            //     client: {
            //       displayName:"Client",
            //     },
            //   },
            //   fields: [

            //   ]
            // });
            // table.render().then(function () {
            //   $('.edit-name').click(function(e) {
            //     e.preventDefault();
            //     dialogProjectEditForm.target = e.target;
                
            //     form = dialogProjectEditForm.find( "form" )
            //     form.find("#name").val($(e.target).data("name"));
            //     debugger;
            //     //form.find("#client-select-popup").val($(e.target).data("client-id")).trigger('change');;
            //     form.find("#client-select-popup").select2("val",'{"clientId": "'+$(e.target).data("client-id")+'", "clientName":"'+$(e.target).data("client-name")+'"}');
                
            //     dialogProjectEditForm.dialog("open");
            // })
  
            // });
            var columns = [
              //{title:"Id", field:"id", width:100}, //column has a fixed width of 100px;
              {title:"#", field:"", formatter:"rownum", width: 40, align: "center"},
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
                
                //form.find("#client-select-popup").val($(e.target).data("client-id")).trigger('change');
                var clientSelectData = {
                  clientId: row.getData().clientId,
                  clientName: row.getData().client,
                };
                
                // var tagSelectData = [{
                //   allowedTags: row.getData().allowedTags,

                // }];

                form.find("#client-select-popup").select2("val",JSON.stringify(clientSelectData));
                form.find("#archived").prop('checked', row.getData().archived);
                if (isAdmin()){
                  var tags = row.getData().origAllowedTags.map(function(e){ return e.id;});
                  var users = row.getData().allowedUsers;
                  form.find("#locked-select-tags").select2("val",tags);
                  form.find("#locked-select-users").select2("val",users);
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