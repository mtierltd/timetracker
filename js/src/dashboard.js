var $ = require("jquery");
require("jquery-migrate");
// var moment = require("moment");
require("jqueryui");
//require("jqueryui/jquery-ui.css");
require("daterangepicker");
var moment = require("moment");

require('daterangepicker/daterangepicker.css');
require('../../css/style.css');
var Chart = require("chart.js");


Chart.plugins.register({
  afterDraw: function(chart) {
  if (chart.data.datasets.length === 0 || chart.data.datasets[0].data.length === 0) {
      // No data is present
    var ctx = chart.chart.ctx;
    var width = chart.chart.width;
    var height = chart.chart.height
    chart.clear();

    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = "16px normal 'Helvetica Nueue'";
    ctx.fillText('No data to display', width / 2, height / 2);
    ctx.restore();
  }
}
});

(function() {
  $.ajaxSetup({
    headers: { 'RequestToken': OC.requestToken }
  });

    $( function() {
        $(document).ready(function() {

          var start = moment().subtract(29, 'days');
          var end = moment();
          var group1 = 'client';
          var group2 = 'project';
          var group3 = '';
          var filterProjectId = '';
          var filterClientId = '';
          var myDoughnutChart = null;


          function cb(start, end) {
            $('#report-range span').html(start.format('DD/MM/YY') + ' - ' + end.format('DD/MM/YY'));
          }
          $("#report-range").daterangepicker({
              timePicker: false,
              startDate: start,
              endDate: end,
              showCustomRangeLabel: false,
              ranges: {
                  'Today': [moment().startOf('day'), moment().endOf('day')],
                  'Last 7 Days': [moment().startOf('day').subtract(6, 'days'), moment().endOf('day')],
                  'Last 30 Days': [moment().startOf('day').subtract(29, 'days'), moment().endOf('day')],
                  'Last 90 Days': [moment().startOf('day').subtract(89, 'days'), moment().endOf('day')],
                  'Last 365 Days': [moment().startOf('day').subtract(364, 'days'), moment().endOf('day')],
                  'This Month': [moment().startOf('month'), moment().endOf('day')],
                  'This Year': [moment().startOf('year'), moment().endOf('day')],
                  'Starting last year': [moment().startOf('year').subtract(1, 'year'), moment().endOf('day')],
                  'Last 3 years': [moment().startOf('day').subtract(3, 'year'), moment().endOf('day')],
                  'Last 5 years': [moment().startOf('day').subtract(5, 'year'), moment().endOf('day')],
              },
              locale: {
                  format: 'DD/MM/YY',
                  firstDay: firstDay
              }
            },cb);
          $("#report-range").on('apply.daterangepicker', function(ev, picker) {
            //days = Math.round((picker.endDate.unix()-picker.startDate.unix()) / 86400);
            start = picker.startDate;
            getData();
          });
          cb(start, end);

       
          var chartData = {};
          getData();
          function getData(){
              var baseUrl = OC.generateUrl('/apps/timetracker/ajax/report?name=&from='+start.unix()+'&to='+end.unix()+'&group1='+group1+'&group2='+group2+'&timegroup='+group3+'&filterProjectId='+filterProjectId+'&filterClientId='+filterClientId);
              var default_colors = [
                                  '#3366CC',
                                  '#DC3912',
                                  '#FF9900',
                                  '#109618',
                                  '#990099',
                                  '#3B3EAC',
                                  '#0099C6',
                                  '#DD4477',
                                  '#66AA00',
                                  '#B82E2E',
                                  '#316395',
                                  '#994499',
                                  '#22AA99',
                                  '#AAAA11',
                                  '#6633CC',
                                  '#E67300',
                                  '#8B0707',
                                  '#329262',
                                  '#5574A6',
                                  '#3B3EAC',
                                  '#C71585',
                                  '#006400',
                                  '#4B0082',
                                  '#8B0000',
                                  '#FF4500',
                                  '#BDB76B',
                                  '#008080',
                                  '#FFE4E1',
                                  '#800000',
                                  '#000080',
                                  '#2F4F4F',
                                  '#FF1493',
                                  '#008000',
                                  '#800080',
                                  '#FF0000',
                                  '#FF8C00',
                                  '#20B2AA',
                                  '#FAF0E6',
                                  '#FFD700',
                                  '#A52A2A',
                                  '#708090',
                                  '#DB7093',
                                  '#808000',
                                  '#9400D3',
                                  '#FFDAB9',
                                  '#7FFFD4'
                                ]
              $.ajax({
                      /*headers: {requesttoken: oc_requesttoken},*/
                      url: baseUrl,
                      method: 'GET',
                      dataType: 'json',
                      success: function (d) {
                        chartData = {
                              labels: [],
                              datasets: [{ data: [], backgroundColor: []}, { data: [], backgroundColor: []},],
                            
                          };
                          var clientMap = {};
                          var nclients = 0;
                          // extract clients in clientMap
                          var totalMinutes = 0;
                          for (var x = 0; x < d.items.length; x++){
                            cid = d.items[x].client;
                            if (cid == null){
                              cid = -1;
                            }
                            if (clientMap[cid] === undefined){
                                clientMap[cid] = {duration:d.items[x].totalDuration, order:nclients, client:(d.items[x].client == null)?'Not Set':d.items[x].client}
                                totalMinutes += d.items[x].totalDuration/60.0;
                                nclients++;
                            } else {
                              clientMap[cid].duration = clientMap[cid].duration + d.items[x].totalDuration;
                              totalMinutes += d.items[x].totalDuration/60.0;
                            }
                          }
                          var mx = 0;
                          var nindex = nclients;

                          var sortable = [];
                          for (var client in clientMap) {
                              sortable.push([client, clientMap[client].order]);
                          }

                          sortable.sort(function(a, b) {
                              return a[1] - b[1];
                          });
                            for (var i = 0; i < sortable.length; i++) {
                              t = sortable[i];
                              key = t[0];
                              
                            // first add clients
                            chartData.datasets[0].data[clientMap[key].order] = clientMap[key].duration / 60.0;
                            chartData.datasets[1].data[clientMap[key].order] = 0;
                            if (clientMap[key].client == -1){
                              chartData.labels[clientMap[key].order] = "Client Not Set";
                              
                            } else {
                              chartData.labels[clientMap[key].order] = clientMap[key].client;
                            }
                            chartData.datasets[0].backgroundColor[clientMap[key].order] = default_colors[clientMap[key].order];
                            chartData.datasets[1].backgroundColor[clientMap[key].order] = default_colors[clientMap[key].order];
                            // add projects for each client
                            
                            for (var x = 0; x < d.items.length; x++){
                              
                              if (d.items[x].client === key || (d.items[x].client == null && key == -1)){
                                chartData.datasets[0].data[nindex] = 0;
                                chartData.datasets[1].data[nindex] = d.items[x].totalDuration/60.0;
                               
                                chartData.datasets[1].backgroundColor[nindex] = default_colors[nindex];
                                chartData.datasets[0].backgroundColor[nindex] = default_colors[nindex];
                                if (d.items[x].project == null){
                                  chartData.labels[nindex] = "Project Not Set";
                                } else {
                                  chartData.labels[nindex] = d.items[x].project;
                                }
                                nindex++;
                              }
                            }
                          };
                          if (myDoughnutChart != null){
                            myDoughnutChart.destroy();
                          }

                          var ctx = document.getElementById("myChart").getContext("2d");

                          myDoughnutChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: chartData,
                            options: {
                              tooltips: {
                                callbacks: {
                                  title: function(tooltipItem, data) {
                                    return data['labels'][tooltipItem[0]['index']];
                                  },
                                  label: function(tooltipItem, data) {

                                    mm =  data['datasets'][tooltipItem.datasetIndex]['data'][tooltipItem['index']];
                                    h = Math.trunc(mm / 60);
                                    m = Math.trunc(mm % 60);
                                    return (h+" hours "+m+" minutes")
                                  },
                                  afterLabel: function(tooltipItem, data) {
                                    var dataset = data['datasets'][tooltipItem.datasetIndex];
                                    if (!(0 in dataset["_meta"]))
                                      return '';
                                    var percent = Math.round((dataset['data'][tooltipItem['index']] / dataset["_meta"][0]['total']) * 100)
                                    return '(' + percent + '%)';
                                  },
                                },

                              }
                            }
                        });
                        h = Math.trunc(totalMinutes / 60);
                        m = Math.trunc(totalMinutes % 60);
                        $('#summary').html('Total time: '+h+" hours "+m+" minutes");
                      }
                  });
          }
      });
      } );
}());