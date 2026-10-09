$.getJSON("assets/json/quiz.json", function (data) {
  $.each(data, function (i, item) {
    $("#content").append(`
      <div class="card question q${i}">
        <div class="card-content">
          <div class='content'>
            <h3 style='color:white;'>${item.title}</h3>
            <div class='control'>
              <label class='radio'>
                <input type='radio' name='${item.title}' class='message'>
                ${item.one}
              </label>
              <br>
                            <label class='radio'>
                <input type='radio' name='${item.title}' class='message'>
                ${item.two}
              </label>
              <br>
              <label class='radio'>
                <input type='radio' name='${item.title}' class='message'>
                ${item.three}
              </label>
              <br>
                            <label class='radio'>
                <input type='radio' name='${item.title}' class='message'>
                ${item.four}
              </label>
              <br>
              <label class='radio'>
                <input type='radio' name='${item.title}' class='message'>
                ${item.five}
              </label>
              <br>
                            <label class='radio'>
                <input type='radio' name='${item.title}' class='message'>
                ${item.six}
              </label>
              <br>
              <label class='radio'>
                <input type='radio' name='${item.title}' class='message'>
                ${item.seven}
              </label>
              <br>
                            <label class='radio'>
                <input type='radio' name='${item.title}' class='message'>
                ${item.eight}
              </label>
              <br>
            </div>
          </div>
        </div>
      </div>
      `);
  });
});

function stringGen(len) {
  var text = "";
  var charset = "abcdefghijklmnopqrstuvwxyz0123456789";
  for (var i = 0; i < len; i++)
    text += charset.charAt(Math.floor(Math.random() * charset.length));
  return text;
}

function notify(msg, mode) {
  var classy = stringGen(9);
  $("body").append(
    `<div id='${classy}' class='notification is-${mode} slideInRight'>${msg}</div>`
  );
  setTimeout(function () {
    $(`#${classy}`).removeClass("slideInRight");
    $(`#${classy}`).addClass("slideOutRight");
    setTimeout(function () {
      $(`#${classy}`).remove();
    }, 3000);
  }, 3000);
}

$(document).ready(function () {
  $("#quiz").bind("submit", function (e) {
    e.preventDefault();

    $('input[type="radio"]').click(function () {
      $(`.button`).prop("disabled", false);
    });

    var all_answers = {};
    $('input[type="radio"]:checked').each(function () {
      var answer = $.trim($(this).parent().text());
      var title = $(this).attr("name");
      all_answers[title] = answer;
    });
    var all_questions = $(".question").length;
    var checked_questions = $('input[type="radio"]:checked').length;
    console.log(checked_questions + "/" + all_questions);
    $("#result").text(checked_questions + "/" + all_questions);
    if (checked_questions == all_questions && checked_questions != 0) {
      $.getJSON("assets/json/quiz_answers.json")
        .done(function (answers) {
          // marking happens in the browser (this is a static site)
          var correct = 0;
          var total = answers.length;
          $.each(answers, function (i, item) {
            if (all_answers[item.title] == item.answer) {
              correct++;
            }
          });
          var percentage = Math.round((correct * 100) / total);
          var messages = {
            none: "This is some text",
            low: "This is some text",
            medium: "This is some text",
            high: "This is some text",
            perfect: "This is some text",
          };
          var message;
          if (percentage == 100) {
            message = messages.perfect;
          } else if (percentage >= 90) {
            message = messages.high;
          } else if (percentage >= 60) {
            message = messages.medium;
          } else if (percentage >= 30) {
            message = messages.low;
          } else if (percentage == 0) {
            message = messages.none;
          } else {
            message = messages.low;
          }
          notify("Here's your results", "white");

          $(".question, #quiz").hide();
          $("#percent").text(`${percentage}%`);
          $("#score").text(`${correct}/${total}`);
          $("#message").text(`${message}`);
          $(".results").show();
        })
        .fail(function () {
          notify("Something went wrong", "white");
        });
    } else {
      notify("Looks like you missed something", "white");
    }
  });
});
