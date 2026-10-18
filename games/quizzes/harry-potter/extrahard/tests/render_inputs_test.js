const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const root = `${__dirname}/..`;
const questions = JSON.parse(fs.readFileSync(`${root}/assets/json/quiz.json`, "utf8"));
const rendered = [];
const jquery = (selector) => selector === "#content"
  ? { append: (html) => rendered.push(html) }
  : { ready: () => {} };
jquery.getJSON = (_path, callback) => callback(questions);
jquery.each = (items, callback) => items.forEach((item, index) => callback(index, item));
vm.runInNewContext(fs.readFileSync(`${root}/assets/js/scripts.js`, "utf8"), {
  $: jquery, document: {}, console, Math, setTimeout,
});

assert.equal(rendered.length, 14, "all 14 question cards render");
const radioIndexes = [8, 12, 13];
const textIndexes = [1, 2, 3, 5, 10];
rendered.forEach((html, index) => {
  assert.match(html, new RegExp(`class="card question q${index}"`));
  if (index === 0 || index === 4) assert.match(html, /type="number"/);
  if (textIndexes.includes(index)) {
    assert.match(html, /type="text"/);
    assert.doesNotMatch(html, /<datalist|<option/i);
  }
  if (radioIndexes.includes(index)) {
    assert.equal((html.match(/type="radio"/g) || []).length, 8);
    assert.match(html, new RegExp(`name="answer-${index}"`));
  }
});
assert.match(rendered[0], /name="era"/);
assert.match(rendered[6], /inputmode="numeric"/);
assert.match(rendered[7], /<select/);
assert.equal((rendered[7].match(/<option value=/g) || []).length, 9);
assert.match(rendered[9], /name="bulgaria"/);
assert.match(rendered[9], /name="ireland"/);
assert.match(rendered[11], /name="galleons"/);
assert.match(rendered[11], /name="sickles"/);
assert.match(rendered[11], /name="knuts"/);

radioIndexes.concat([7]).forEach((index) => {
  ["one", "two", "three", "four", "five", "six", "seven", "eight"].forEach((optionName) => {
    assert.ok(rendered[index].includes(questions[index][optionName]), `question ${index + 1} retains ${optionName}`);
  });
});

console.log("All Extra Hard quiz input rendering tests passed.");
