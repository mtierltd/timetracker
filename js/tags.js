

(function() {

    $( function() {

        $(document).ready(function() {
            $("#dialog-confirm").dialog({
              autoOpen: false,
              modal: true
            });
          });
        $("#new-tag-submit").click(function () {
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
        });
        dialogTagEditForm = $( "#dialog-tag-edit-form" ).dialog({
            autoOpen: false,
            height: 400,
            width: 350,
            modal: true,
            buttons: {
              "Edit tag": {click: function(){
                  editTag(dialogTagEditForm);
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
       
          form = dialogTagEditForm.find( "form" ).on( "submit", function( event ) {
            event.preventDefault();
            editTag(dialogTagEditForm);
          });

        getTags();
        function editTag(dialogTagEditForm){
            target = dialogTagEditForm.target;
            form =  dialogTagEditForm.find( "form" );
            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/edit-tag/'+target.id);
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
            $.getJSON( baseUrl, function( data ) {
                
                var tags = [];
                $.each( data.Tags, function( id, tagMap ) {
                   
                  tags.push( "<div class='tag-button'>" +
                                        "<div class='tag-name' id='tag-name-"+tagMap['id']+"'>" + 
                                        tagMap['name'] +
                                        "</div>"+
                                        "<div class='controls'>"+
                                            "<span class='fas fa-edit clickable tag-edit' id='"+tagMap['id']+"' data-name='"+tagMap['name']+"'></span>"+
                                            "<span class='fas fa-times clickable tag-delete' id='"+tagMap['id']+"'></span>"+
                                        "</div>" + 
                                    "</div>" );
                  });

                $("#tags").html($( "<div/>", {
                      "class": "tags-list",
                      html: tags.join( "" )
                    }))
                $('.tag-edit').click(function(e) {
                    e.preventDefault();
                    dialogTagEditForm.target = e.target;
                    
                    form = dialogTagEditForm.find( "form" )
                    form.find("#name").val($(e.target).data("name"));
                    dialogTagEditForm.dialog("open");
                    
                    

                })
                $('.tag-delete').click(function(e) {
                    $("#dialog-confirm").dialog({
                        buttons : {
                          "Confirm" : {click: function() {
                            var baseUrl = OC.generateUrl('/apps/timetracker/ajax/delete-tag/'+e.target.id);
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
                          },
                          text: 'Confirm',
                          class:'primary'
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
