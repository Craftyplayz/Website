export function renderDescriptorQuestion(view, screen, model) {
  const { snapshot } = model;
  const resolved = snapshot.state === 'answer-feedback';
  const stage = view.node('h2', 'Read the passage and fill in the missing expressive descriptor.', 'stage-label');
  stage.id = 'stageLabel';
  stage.tabIndex = -1;
  const currentNumber = resolved ? snapshot.answered : snapshot.answered + 1;
  const progress = view.node('p', `Question ${Math.min(currentNumber, snapshot.questionCount)} of ${snapshot.questionCount} · ${snapshot.answered} answered`, 'run-label');
  progress.id = 'questionProgress';
  const passage = view.node('blockquote', '', 'passage-card descriptor-passage');
  const parts = (snapshot.question?.passage || '').split('______');
  parts.forEach((text, index) => {
    if (index) {
      const blank = view.node('span', '______', 'descriptor-blank');
      blank.setAttribute('aria-label', 'missing descriptor');
      passage.append(blank);
    }
    passage.append(view.document.createTextNode(text));
  });
  passage.id = 'passageCard';
  passage.tabIndex = 0;
  const form = view.node('form', '', 'descriptor-form');
  const label = view.node('label', 'Missing expressive descriptor');
  label.htmlFor = 'descriptorAnswer';
  const input = view.node('input', '', 'name-input');
  input.id = 'descriptorAnswer';
  input.name = 'descriptor';
  input.type = 'text';
  input.value = model.answerDraft;
  input.placeholder = 'Type the missing descriptor…';
  input.maxLength = 80;
  input.autocomplete = 'off';
  input.spellcheck = false;
  input.setAttribute('aria-describedby', 'descriptorHint');
  input.disabled = resolved || model.pending;
  input.addEventListener('input', () => { model.answerDraft = input.value; });
  const hint = view.node('p', 'The blank replaces only the original speech descriptor. Small spelling mistakes may count; different words do not.', 'field-hint');
  hint.id = 'descriptorHint';
  const submit = view.button('Submit Answer', 'submitDescriptor', () => {}, 'btn btn-primary');
  submit.type = 'submit';
  submit.disabled = resolved || model.pending;
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (!resolved && !model.pending) view.controller.answer('descriptor', input.value);
  });
  form.append(label, input, hint, submit);
  const feedback = view.node('div', '', 'feedback-bar show fb-info');
  feedback.id = 'feedbackBar';
  feedback.setAttribute('role', 'status');
  if (resolved && snapshot.feedback) {
    feedback.textContent = `${snapshot.feedback.correct ? '✦ Accepted — one point!' : 'Not quite — no point.'} Original descriptor: ${snapshot.feedback.descriptor}.`;
  } else if (model.pending) feedback.textContent = 'Checking with the server…';
  screen.append(stage, progress, passage, form, feedback);
  if (model.error) view.notice(screen, model.error, true);
  if (resolved) {
    const next = view.button(snapshot.answered === snapshot.questionCount ? 'Finish challenge' : 'Next question →',
      'nextQuestionButton', () => view.controller.next(), 'btn btn-primary');
    next.disabled = model.pending;
    screen.append(next);
  }
  screen.append(view.button('Abandon · return to menu', 'quizMenuButton', () => view.controller.menu()));
}
