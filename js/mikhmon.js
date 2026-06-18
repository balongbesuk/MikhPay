function RequiredV() {
  var e = document.getElementById("expmode").value,
      t = document.getElementById("validity").style,
      n = document.getElementById("validi");
  if (e === "rem" || e === "remc" || e === "ntf" || e === "ntfc") {
    t.display = "table-row";
    n.type = "text";
    if (n.value === "") {
      n.value = "";
    }
    $("#validi").focus();
  } else {
    t.display = "none";
    n.type = "hidden";
  }
}

function defUserl() {
  var e = document.getElementById("user").value,
      t = document.getElementById("num").style,
      n = document.getElementById("lower").style,
      l = document.getElementById("upper").style,
      o = document.getElementById("upplow").style,
      i = document.getElementById("lower1").style,
      d = document.getElementById("upper1").style,
      a = document.getElementById("upplow1").style,
      c = document.getElementById("mix").style,
      m = document.getElementById("mix1").style,
      s = document.getElementById("mix2").style;
  
  if (e === "up") {
    $("select[name=userl] option:first").html("4");
    $("select[name=char] option:first").html("Random abcd");
    n.display = "block";
    l.display = "block";
    o.display = "block";
    i.display = "none";
    d.display = "none";
    a.display = "none";
    t.display = "none";
    c.display = "block";
    m.display = "block";
    s.display = "block";
  } else if (e === "vc") {
    $("select[name=userl] option:first").html("8");
    $("select[name=char] option:first").html("Random abcd2345");
    n.display = "none";
    l.display = "none";
    o.display = "none";
    i.display = "block";
    d.display = "block";
    a.display = "block";
    t.display = "block";
    c.display = "block";
    m.display = "block";
    s.display = "block";
  }
}

function loader() {
  document.getElementById("loader").style = "display:inline;";
}

function notify(e) {
  var t = $("#notify");
  t.find(".message").text(e);
  t.show();
  var n = $(".message").text(),
      l = 0;
  setInterval(function() {
    $(".message").append("●");
    if (++l == 4) {
      $(".message").html(n);
      l = 0;
    }
  }, 500);
}

function printBT() {
  window.location = "my.bluetoothprint.scheme://";
}

function connect(e) {
  $("#temp").load("./admin.php?id=connect&session=" + e);
}

function stheme(e) {
  $("#temp").load(e);
}

function dellSelected(e) {
  $("#temp").load(e);
}

function loadpage(e) {
  $("#temp").load(e);
}

function sortTable(e, t, n) {
  var l, o = e.tBodies[0],
      i = Array.prototype.slice.call(o.rows, 0);
  n = -(+n || -1);
  i = i.sort(function(e, l) {
    return n * e.cells[t].textContent.trim().localeCompare(l.cells[t].textContent.trim());
  });
  for (l = 0; l < i.length; ++l) {
    o.appendChild(i[l]);
  }
}

function makeSortable(e) {
  var t, n = e.tHead;
  if (n && (n = n.rows[0]) && (n = n.cells), n) {
    for (t = n.length; --t >= 0;) {
      !function(t) {
        var l = 1;
        n[t].addEventListener("click", function() {
          sortTable(e, t, l = 1 - l);
        });
      }(t);
    }
  }
}

function makeAllSortable(e) {
  var t = (e = e || document.body).getElementsByTagName("table"),
      n = t.length;
  for (; --n >= 0;) {
    makeSortable(t[n]);
  }
}

$(".main-container").fadeIn(400);
$("#loading").hide();

var idleto, idto = document.getElementById("idto").innerHTML;

function idleTimer() {
  var e = document.getElementById("timer");
  function t() {
    e.innerHTML = idleto;
  }
  window.onmousemove = t;
  window.onmousedown = t;
  window.onclick = t;
  window.onscroll = t;
  window.onkeypress = t;
}

function startTimer() {
  var e = document.getElementById("logout"),
      t = document.getElementById("timer"),
      n = t.innerHTML.split(/[:]+/),
      l = n[0],
      o = checkSecond(n[1] - 1);
  if (o == 59) {
    l -= 1;
  }
  if (l == 0 && o == 0) {
    t.innerHTML = "0:00";
    e.click();
  }
  t.innerHTML = l + ":" + o;
  setTimeout(startTimer, 1000);
}

function checkSecond(e) {
  if (e < 10 && e >= 0) {
    e = "0" + e;
  }
  if (e < 0) {
    e = "59";
  }
  return e;
}

idleto = (idto == "" || idto == "0") ? "10:00" : idto + ":00";
document.getElementById("timer").innerHTML = idleto;

var url = window.location.href,
    getID = url.split("=")[1];

if (getID !== "login" && idto !== "disable") {
  idleTimer();
  startTimer();
}
