function everything() {
	var x = document.getElementById("read").getElementsByClassName("gallery-item");
	var i;
	for (i = 0; i < x.length; i++) {
		x[i].style.display = "block";
	}
}

function sort(parameter) {
	var x = document.getElementById("read").getElementsByClassName("gallery-item");
	var i;
	for (i = 0; i < x.length; i++) {
		x[i].style.display = "none";
	}
	x = document.getElementById("read").getElementsByClassName("gallery-item " + parameter);
	for (i = 0; i < x.length; i++) {
		x[i].style.display = "block";
	}
}

/* function expand(parameter) {
	var x = document.getElementById("read").getElementsByClassName("gallery-item " + parameter);
	var i;
	for (i = 0; i < x.length; i++) {
		x[i].style.display = "block";
	}
} */