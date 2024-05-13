var tenths = 0;
var seconds = 0;
var minutes = 0;
var timerId;

function timer(parameter) {
    document.getElementById("start").disabled = true;
    document.getElementById("stop").disabled = false;
    document.getElementsByClassName("reading")[0].style.display = "block";
    document.getElementById("text").scrollIntoView();
    timerId = setInterval(function() {
        tenths++;
        if (tenths > 9) {
            tenths = 0;
            seconds++;
        }
        if (seconds > 59) {
            seconds = 0;
            minutes++;
        }
        minutestotal = minutes + seconds / 60 + tenths / 600;
        document.getElementById("speed").innerHTML = (parameter / minutestotal).toFixed(0) + " Words per minute";
        document.getElementById("time").innerHTML = minutes + ":" + seconds + "." + tenths;
    }, 100);

}

function stop() {
    clearInterval(timerId);
    document.getElementById("stop").disabled = true;
    document.getElementById("start").disabled = false;
    document.getElementById("start").scrollIntoView();
}