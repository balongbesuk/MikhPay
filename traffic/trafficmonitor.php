<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {


}
?>
<script>
  $(function() {
    $("#d_interface").on("change", function() {
      var val = $(this).val();
      var sessionName = "Interface_" + document.getElementById("MikhmonSession").value;
      if (val) {
        if (typeof(Storage) !== "undefined") {
          sessionStorage.setItem(sessionName, val);
        } else {
          alert("Please use Google Chrome");
        }
        window.location.reload();
      }
      return false;
    });
  });
</script>
          <div class="card">
            <div class="card-header"><h3><i class="fa fa-area-chart"></i> <?= $_traffic_monitor ?> </h3></div>
          
              <div class="card-body">
                <div class="row">
                  <?php $getinterface = $API->comm("/interface/print");
                  $interface = $getinterface[$iface - 1]['name'];
                  $TotalReg = count($getinterface);

                  ?>
                  <div class="col-12">
                  <select id="d_interface" class="dropd pd-5" >
                    <option><?= $_select_interface ?></option>
                    <?php 
                      for ($i = 0; $i < $TotalReg; $i++) {
                        echo '<option value="' . $getinterface[$i]['name'] . '">['.($i+1).'] ' . $getinterface[$i]['name'] . '</option>';
                    }
                    ?>
                  </select>
                  </div>
                  <script type="text/javascript"> 
                    var chart;
                    var sessiondata = "<?= $session ?>";

                    function requestDatta(session,iface) {
                      $.ajax({
                        url: './traffic/traffic.php?session='+session+'&iface='+iface,
                        datatype: "json",
                        success: function(data) {
                          var midata = JSON.parse(data);
                          if( midata.length > 0 ) {
                            var TX=parseInt(midata[0].data);
                            var RX=parseInt(midata[1].data);
                            var x = (new Date()).getTime(); 
                            shift=chart.series[0].data.length > 19;
                            chart.series[0].addPoint([x, TX], true, shift);
                            chart.series[1].addPoint([x, RX], true, shift);
                          }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) { 
                          console.error("Status: " + textStatus + " request: " + XMLHttpRequest); console.error("Error: " + errorThrown); 
                        }       
                      });
                    }	

                    $(document).ready(function() {
                        Highcharts.setOptions({
                          global: {
                            useUTC: false
                          },
                          chart: {
                            height: 500,

                          },
                        });

                        Highcharts.addEvent(Highcharts.Series, 'afterInit', function () {
	                        this.symbolUnicode = {
    	                    circle: '●',
                          diamond: '♦',
                          square: '■',
                          triangle: '▲',
                          'triangle-down': '▼'
                          }[this.symbol] || '●';
                        });

                          chart = new Highcharts.Chart({
                          chart: {
                          renderTo: 'trafficMonitor',
                          animation: Highcharts.svg,
                          type: 'areaspline',
                          events: {
                            load: function () {
                              setInterval(function () {
                                var sesIface = "Interface_" + document.getElementById("MikhmonSession").value;
                                var interface = sessionStorage.getItem(sesIface);
                                requestDatta(sessiondata,interface);
                                chart.setTitle({ text: '<?= $_interface ?> ' + interface });
                              }, 3000);
                            }				
                          }
                        },
                        title: {
                          text: '<?= $_loading_interface ?>...'
                        },
                        
                        xAxis: {
                          type: 'datetime',
                          tickPixelInterval: 150,
                          maxZoom: 20 * 1000,
                        },
                        yAxis: {
                            minPadding: 0.2,
                            maxPadding: 0.2,
                            title: {
                              text: null
                            },
                            labels: {
                              formatter: function () {      
                                var bytes = this.value;                          
                                var sizes = ['bps', 'kbps', 'Mbps', 'Gbps', 'Tbps'];
                                if (bytes == 0) return '0 bps';
                                var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
                                return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];                    
                              },
                            },       
                        },
                        
                        series: [{
                          name: 'Tx',
                          data: [],
                          marker: {
                            symbol: 'circle'
                          }
                        }, {
                          name: 'Rx',
                          data: [],
                          marker: {
                            symbol: 'circle'
                          }
                        }],

                        tooltip: {
                          formatter: function () { 
                            var s = [];
                            $.each(this.points, function(i, point) {
                              var y = point.y;
                              var units = ["bps", "kbps", "Mbps", "Gbps", "Tbps"];
                              if (y == 0) {
                                s.push("<span style=\"color:" + this.series.color + "; font-size: 1.5em;\">" + this.series.symbolUnicode + "</span><b>" + this.series.name + ":</b> 0 bps");
                              } else {
                                var unitIndex = parseInt(Math.floor(Math.log(y) / Math.log(1024)));
                                s.push("<span style=\"color:" + this.series.color + "; font-size: 1.5em;\">" + this.series.symbolUnicode + "</span><b>" + this.series.name + ":</b> " + parseFloat((y / Math.pow(1024, unitIndex)).toFixed(2)) + " " + units[unitIndex]);
                              }
                            });
                            return "<b>MikhTrans Traffic Monitor</b><br /><br /><b>Time: </b>" + Highcharts.dateFormat("%H:%M:%S", new Date(this.x)) + "<br />" + s.join(" <br/> ");
                          },
                          shared: true                                                      
                        },
                      });
                    });
                  </script>
                  <div class="col-12" id="trafficMonitor"></div>
                </div>
              </div>  