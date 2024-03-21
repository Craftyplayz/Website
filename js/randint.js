let minimum;
let maximum;
let amount;

function generate() {
    return Math.floor(Math.random() * (maximum - minimum + 1)) + minimum;
}
document.getElementById("generate").addEventListener("click", function() {
    minimum = document.getElementById("minimum").value;
    maximum = document.getElementById("maximum").value;
    amount = document.getElementById("amount").value;
    let output = "";
    for (let i = 0; i < amount; i++) {
        output += generate() + "\n";
    }
    document.getElementById("output").value = output;
})

