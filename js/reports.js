(function() {

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
                format: 'DD/MM/YY'
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
          $('#group1').on("change", function(e) { 
            group1 = e.val;
            getReport();
          });
          $('#group2').on("change", function(e) {
            group2 = e.val;
            getReport();
          });
          $('#group3').on("change", function(e) { 
            group3 = e.val;
            getReport();
          });
          getReport();
          $("#filter-project").select2({
            tags: true,
            width: '200px',
            escapeMarkup : function(markup) { return markup; },
            placeholder: "Select project",
            allowClear: true,
            ajax: { 
              tags: true,
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

        $("#filter-project").on("change", function (e) { 
         
          filterProjectId = $(e.target).val();
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
                  results = [];
                  results.push({
                      id: clientId,
                      text: clientName,
                      });
                  
                  callback(results[0]);
          }
      });

      $("#filter-client").on("change", function (e) { 
       
        filterClientId = $(e.target).val();
        getReport();
      });
        $('input.select2-input').attr('autocomplete', "xxxxxxxxxxx");

        
       
          function getReport(){
              var baseUrl = OC.generateUrl('/apps/timetracker/ajax/report?name=&from='+start.unix()+'&to='+end.unix()+'&group1='+group1+'&group2='+group2+'&timegroup='+group3+'&filterProjectId='+filterProjectId+'&filterClientId='+filterClientId);
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
                    
                    return h + ':' + m + ':' + s;
                    
                  },}, //column will be allocated 1/5 of the remaining space
              ],
                ajaxResponse:function(url, params, response){
          
                  return response.items; //return the tableData property of a response json object
              },
              });
              $("#download-csv").click(function(){
                table.download("csv", "data.csv");
                return false;
            });
            $("#download-json").click(function(){
              table.download("json", "data.json");
              return false;
            });
          }
      });


      } );
}());