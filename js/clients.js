

(function() {


    $( function() {

        $(document).ready(function() {
            $("#dialog-confirm").dialog({
              autoOpen: false,
              modal: true
            });
          });
        $("#new-client-submit").click(function () {
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
        });
        dialogClientEditForm = $( "#dialog-client-edit-form" ).dialog({
            autoOpen: false,
            height: 400,
            width: 350,
            modal: true,
            buttons: {
              "Edit client": {click:function(){
                  editClient(dialogClientEditForm);
              },
              text: 'Edit client',
              class: 'primary'
            },
              Cancel: function() {
                dialogClientEditForm.dialog( "close" );
              }
            },
            close: function() {
              form[ 0 ].reset();
            }
          });
       
          form = dialogClientEditForm.find( "form" ).on( "submit", function( event ) {
            event.preventDefault();
            editClient(dialogClientEditForm);
          });

        getClients();
        function editClient(dialogClientEditForm){
            target = dialogClientEditForm.target;
            form =  dialogClientEditForm.find( "form" );
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/edit-client/'+target.id);
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
            $.getJSON( baseUrl, function( data ) {
                
                var clients = [];
                $.each( data.Clients, function( id, clientMap ) {
                   
                    clients.push( "<div class='client-button'>" +
                                        "<div class='client-name' id='client-name-"+clientMap['id']+"'>" + 
                                            clientMap['name'] +
                                        "</div>"+
                                        "<div class='controls'>"+
                                            "<span class='fas fa-edit clickable client-edit' id='"+clientMap['id']+"' data-name='"+clientMap['name']+"'></span>"+
                                            "<span class='fas fa-times clickable client-delete' id='"+clientMap['id']+"'></span>"+
                                        "</div>" + 
                                    "</div>" );
                  });
                $("#clients").html($( "<div/>", {
                      "class": "clients-list",
                      html: clients.join( "" )
                    }))
                $('.client-edit').click(function(e) {
                    e.preventDefault();
                    dialogClientEditForm.target = e.target;
                    
                    form = dialogClientEditForm.find( "form" )
                    form.find("#name").val($(e.target).data("name"));
                    dialogClientEditForm.dialog("open");
                    
                    

                })
                $('.client-delete').click(function(e) {
                    $("#dialog-confirm").dialog({
                        buttons : {
                          "Confirm" : { click:function() {
                            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/delete-client/'+e.target.id);
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
                                    .always(function() {
                                    
                                    });
                          },
                        text: 'Confirm',
                        class: 'primary'
                        },
                          "Cancel" : function() {
                            $(this).dialog("close");
                          }
                        }
                      });
                    $("#dialog-confirm").dialog("open");
                })
              });
        }


      } );
}());