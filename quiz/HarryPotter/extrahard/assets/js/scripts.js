const textQuestionIndexes = new Set([1, 2, 3, 5, 10]);
const choiceQuestionIndexes = new Set([7, 8, 12, 13]);

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (character) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[character]);
}

function renderInput(item, index) {
  const id = `answer-${index}`;
  if (textQuestionIndexes.has(index)) {
    return `<label for="${id}">Your answer</label><input class="input answer-input" id="${id}" name="answer" type="text" autocomplete="off" placeholder="Type your answer">`;
  }
  if (index === 0) {
    return `<label for="${id}">Year</label><input class="input answer-input" id="${id}" name="year" type="number" min="1" step="1" required><label for="era-${index}">Era</label><select class="select answer-input" id="era-${index}" name="era" required><option value="">Choose BC or AD</option><option value="BC">BC</option><option value="AD">AD</option></select>`;
  }
  if (index === 4) {
    return `<label for="${id}">Number of times</label><input class="input answer-input" id="${id}" name="number" type="number" min="0" step="1" required>`;
  }
  if (index === 6) {
    return `<label for="${id}">World Cup number</label><input class="input answer-input" id="${id}" name="number" type="text" inputmode="numeric" required>`;
  }
  if (index === 9) {
    return `<label for="bulgaria-${index}">Bulgaria</label><input class="input answer-input" id="bulgaria-${index}" name="bulgaria" type="number" min="0" step="1" required><label for="ireland-${index}">Ireland</label><input class="input answer-input" id="ireland-${index}" name="ireland" type="number" min="0" step="1" required>`;
  }
  if (index === 11) {
    return ["Galleons", "Sickles", "Knuts"].map((unit) => {
      const name = unit.toLowerCase();
      return `<label for="${name}-${index}">${unit}</label><input class="input answer-input" id="${name}-${index}" name="${name}" type="number" min="0" step="1" required>`;
    }).join("");
  }
  const options = Array.from({ length: 8 }, (_, optionIndex) => item[` ${["one", "two", "three", "four", "five", "six", "seven", "eight"][optionIndex]}`] || item[["one", "two", "three", "four", "five", "six", "seven", "eight"][optionIndex]])
    .filter(Boolean);
  if (index === 7) {
    return `<label for="${id}">Choose a floor</label><select class="select answer-input" id="${id}" name="answer" required><option value="">Select an option</option>${options.map((option) => `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`).join("")}</select>`;
  }
  return `<fieldset><legend>Choose one answer</legend>${options.map((option, optionIndex) => `<label class="radio choice"><input class="answer-input" type="radio" name="answer" value="${escapeHtml(option)}" required> ${escapeHtml(option)}</label>${optionIndex < options.length - 1 ? "<br>" : ""}`).join("")}</fieldset>`;
}

$.getJSON("assets/json/quiz.json", function (data) {
  $.each(data, function (index, item) {
    $("#content").append(`<div class="card question q${index}"><div class="card-content"><div class="content"><h3>${escapeHtml(item.title)}</h3><div class="control">${renderInput(item, index)}</div></div></div></div>`);
  });
});

function stringGen(len) {
  var text = "";
  var charset = "abcdefghijklmnopqrstuvwxyz0123456789";
  for (var i = 0; i < len; i++) text += charset.charAt(Math.floor(Math.random() * charset.length));
  return text;
}

function notify(msg, mode) {
  var classy = stringGen(9);
  $("body").append(`<div id='${classy}' class='notification is-${mode} slideInRight' role="status">${msg}</div>`);
  setTimeout(function () {
    $(`#${classy}`).removeClass("slideInRight").addClass("slideOutRight");
    setTimeout(function () { $(`#${classy}`).remove(); }, 3000);
  }, 3000);
}

function collectAnswers() {
  const answers = {};
  let complete = true;
  $(".question").each(function (index) {
    const fields = $(this).find(".answer-input");
    const answer = {};
    fields.each(function () {
      if (this.type === "radio") {
        if (this.checked) answer.answer = this.value;
      } else {
        answer[this.name] = $(this).val();
      }
    });
    if (Object.keys(answer).length === 0 || fields.filter(function () { return this.type !== "radio"; }).toArray().some((field) => !field.value.trim())) {
      complete = false;
    }
    answers[index] = answer;
  });
  return { answers, complete };
}

$(document).ready(function () {
  let submitted = false;
  $("#quiz").on("submit", function (event) {
    event.preventDefault();
    if (submitted) return;
    const result = collectAnswers();
    if (!result.complete || Object.keys(result.answers).length !== $(".question").length) {
      notify("Looks like you missed something", "white");
      return;
    }
    submitted = true;
    $(".submit").prop("disabled", true);
    $.ajax({
      type: "POST",
      url: "./assets/php/check_quiz.php",
      data: { all_answers: result.answers },
      success: function (data) {
        try {
          const item = JSON.parse(data);
          $("#quiz, .question").hide();
          $("#percent").text(`${item.percentage}%`);
          $("#score").text(`${item.correct}/${item.total}`);
          $("#message").text(item.message);
          $(".results").show();
        } catch (error) {
          submitted = false;
          $(".submit").prop("disabled", false);
          notify("Something went wrong", "white");
        }
      },
      error: function () {
        submitted = false;
        $(".submit").prop("disabled", false);
        notify("Something went wrong", "white");
      },
    });
  });
});
