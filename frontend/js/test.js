// ═══════════════════════════════════════════════════════════════════════════
// StudyPulse - Test Module JavaScript
// ═══════════════════════════════════════════════════════════════════════════

(function () {
    'use strict';

    // ─── State ──────────────────────────────────────────────────────────────
    let testState = {
        tests: [],
        currentTestId: null,
        currentAttemptId: null,
        questions: [],
        currentQuestionIndex: 0,
        answers: {},           // { questionId: 'A'|'B'|'C'|'D' }
        startTime: null,
        durationMinutes: 0,
        timerInterval: null,
        isSubmitting: false
    };

    // ─── API Helpers ────────────────────────────────────────────────────────

    function testApiFetch(path, options) {
        return apiFetch(path, options);
    }

    // ─── Test List ──────────────────────────────────────────────────────────

    window.loadTestList = async function () {
        var container = document.getElementById('test-list-container');
        if (!container) return;

        container.innerHTML = '<div class="test-loading"><div class="test-spinner"></div><div class="test-loading-text">Loading tests...</div></div>';

        try {
            var tests = await testApiFetch('/api/test/list');
            testState.tests = tests;
            renderTestList(tests);
        } catch (err) {
            container.innerHTML = '<div class="test-loading"><div class="test-loading-text">Failed to load tests. Please try again.</div></div>';
            console.error('Failed to load tests:', err);
        }
    };

    function renderTestList(tests) {
        var container = document.getElementById('test-list-container');
        if (!tests || tests.length === 0) {
            container.innerHTML = '<div class="test-loading"><span class="material-symbols-outlined" style="font-size:48px;color:#c6c5d4">quiz</span><div class="test-loading-text">No tests available yet.</div></div>';
            return;
        }

        var html = tests.map(function (t) {
            var lastAttempt = t.last_attempt;
            var actionsHtml = '';

            if (lastAttempt && lastAttempt.status === 'IN_PROGRESS') {
                actionsHtml = '<button class="test-btn-resume" onclick="resumeTest(' + t.test_id + ', ' + lastAttempt.attempt_id + ')"><span class="material-symbols-outlined">play_arrow</span> Resume Test</button>';
            } else if (lastAttempt && (lastAttempt.status === 'SUBMITTED' || lastAttempt.status === 'AUTO_SUBMITTED')) {
                actionsHtml = '<button class="test-btn-result" onclick="viewTestResult(' + lastAttempt.attempt_id + ')"><span class="material-symbols-outlined">assessment</span> View Result</button>' +
                    '<button class="test-btn-start" onclick="startNewTest(' + t.test_id + ')"><span class="material-symbols-outlined">refresh</span> Retake</button>';
            } else {
                actionsHtml = '<button class="test-btn-start" onclick="startNewTest(' + t.test_id + ')"><span class="material-symbols-outlined">play_arrow</span> Start Test</button>';
            }

            return '<div class="test-card">' +
                '<div class="test-card-header">' +
                '<h3 class="test-card-title">' + escapeHtml(t.title) + '</h3>' +
                '</div>' +
                '<div class="test-card-meta">' +
                '<div class="test-card-meta-item"><span class="material-symbols-outlined">help_outline</span>' + t.total_questions + ' Questions</div>' +
                '<div class="test-card-meta-item"><span class="material-symbols-outlined">timer</span>' + t.duration_minutes + ' Minutes</div>' +
                '<div class="test-card-meta-item"><span class="material-symbols-outlined">star</span>' + t.total_marks + ' Marks</div>' +
                '</div>' +
                '<div class="test-card-actions">' + actionsHtml + '</div>' +
                '</div>';
        }).join('');

        container.innerHTML = html;
    }

    // ─── Start Test ─────────────────────────────────────────────────────────

    window.startNewTest = async function (testId) {
        try {
            var data = await testApiFetch('/api/test/start', {
                method: 'POST',
                body: JSON.stringify({ test_id: testId })
            });

            testState.currentTestId = testId;
            testState.currentAttemptId = data.attempt_id;
            testState.startTime = data.start_time;
            testState.durationMinutes = data.duration_minutes;
            testState.answers = {};
            testState.currentQuestionIndex = 0;

            // If resumed, load saved answers
            if (data.resumed) {
                await loadSavedAnswers(data.attempt_id);
            }

            await loadQuestions(testId);
            showTestInterface();
        } catch (err) {
            alert('Failed to start test: ' + err.message);
        }
    };

    window.resumeTest = async function (testId, attemptId) {
        try {
            var data = await testApiFetch('/api/test/resume/' + attemptId);

            if (data.auto_submitted) {
                alert('Time expired. Your test has been auto-submitted.');
                viewTestResult(attemptId);
                return;
            }

            if (data.status !== 'IN_PROGRESS') {
                alert('This test has already been submitted.');
                viewTestResult(attemptId);
                return;
            }

            testState.currentTestId = data.test_id;
            testState.currentAttemptId = data.attempt_id;
            testState.startTime = data.start_time;
            testState.durationMinutes = data.duration_minutes;
            testState.answers = data.saved_answers || {};
            testState.currentQuestionIndex = 0;

            // Convert string keys to int keys
            var convertedAnswers = {};
            for (var key in testState.answers) {
                convertedAnswers[parseInt(key)] = testState.answers[key];
            }
            testState.answers = convertedAnswers;

            await loadQuestions(data.test_id);
            showTestInterface();
        } catch (err) {
            alert('Failed to resume test: ' + err.message);
        }
    };

    async function loadSavedAnswers(attemptId) {
        try {
            var data = await testApiFetch('/api/test/resume/' + attemptId);
            if (data.saved_answers) {
                var convertedAnswers = {};
                for (var key in data.saved_answers) {
                    convertedAnswers[parseInt(key)] = data.saved_answers[key];
                }
                testState.answers = convertedAnswers;
            }
        } catch (err) {
            console.error('Failed to load saved answers:', err);
        }
    }

    async function loadQuestions(testId) {
        var questions = await testApiFetch('/api/test/' + testId + '/questions');
        testState.questions = questions;
    }

    // ─── Test Interface ─────────────────────────────────────────────────────

    function showTestInterface() {
        // Hide the test list section and show the test interface
        var testListSection = document.getElementById('app-test');
        var testInterface = document.getElementById('app-test-interface');
        var testResult = document.getElementById('app-test-result');

        if (testListSection) testListSection.classList.add('hidden');
        if (testResult) testResult.classList.add('hidden');
        if (testInterface) testInterface.classList.remove('hidden');

        renderQuestionSidebar();
        renderCurrentQuestion();
        startTimer();
    }

    function renderQuestionSidebar() {
        var grid = document.getElementById('test-q-grid');
        if (!grid) return;

        var html = '';
        for (var i = 0; i < testState.questions.length; i++) {
            var q = testState.questions[i];
            var cls = 'test-q-btn';
            if (i === testState.currentQuestionIndex) cls += ' current';
            if (testState.answers[q.question_id]) cls += ' answered';
            html += '<button class="' + cls + '" onclick="goToQuestion(' + i + ')">' + (i + 1) + '</button>';
        }
        grid.innerHTML = html;

        // Update answered count
        var answeredCount = Object.keys(testState.answers).length;
        var totalCount = testState.questions.length;
        var answeredEl = document.getElementById('test-answered-count');
        var answeredTopEl = document.getElementById('test-answered-count-top');
        if (answeredEl) answeredEl.textContent = answeredCount + '/' + totalCount;
        if (answeredTopEl) answeredTopEl.textContent = answeredCount + '/' + totalCount;
    }

    function renderCurrentQuestion() {
        var idx = testState.currentQuestionIndex;
        var q = testState.questions[idx];
        if (!q) return;

        var numEl = document.getElementById('test-current-q-number');
        var textEl = document.getElementById('test-current-q-text');
        var diffEl = document.getElementById('test-current-q-difficulty');
        var optionsEl = document.getElementById('test-current-q-options');
        var prevBtn = document.getElementById('test-prev-btn');
        var nextBtn = document.getElementById('test-next-btn');

        if (numEl) numEl.textContent = 'Question ' + (idx + 1) + ' of ' + testState.questions.length;
        if (textEl) textEl.textContent = q.question_text;

        if (diffEl) {
            var diffClass = 'test-difficulty-badge ' + q.difficulty_level.toLowerCase();
            diffEl.className = diffClass;
            diffEl.textContent = q.difficulty_level;
        }

        var selectedOption = testState.answers[q.question_id] || null;
        var options = [
            { key: 'A', text: q.option_a },
            { key: 'B', text: q.option_b },
            { key: 'C', text: q.option_c },
            { key: 'D', text: q.option_d }
        ];

        var optHtml = options.map(function (opt) {
            var selClass = (selectedOption === opt.key) ? ' selected' : '';
            return '<div class="test-option-item' + selClass + '" onclick="selectOption(\'' + opt.key + '\')">' +
                '<div class="test-option-radio"><div class="test-option-radio-inner"></div></div>' +
                '<div class="test-option-label-key">' + opt.key + '</div>' +
                '<div class="test-option-text">' + escapeHtml(opt.text) + '</div>' +
                '</div>';
        }).join('');

        if (optionsEl) optionsEl.innerHTML = optHtml;

        // Navigation buttons
        if (prevBtn) prevBtn.disabled = (idx === 0);
        if (nextBtn) nextBtn.disabled = (idx === testState.questions.length - 1);
    }

    window.goToQuestion = function (index) {
        if (index < 0 || index >= testState.questions.length) return;
        testState.currentQuestionIndex = index;
        renderQuestionSidebar();
        renderCurrentQuestion();
    };

    window.nextQuestion = function () {
        if (testState.currentQuestionIndex < testState.questions.length - 1) {
            goToQuestion(testState.currentQuestionIndex + 1);
        }
    };

    window.prevQuestion = function () {
        if (testState.currentQuestionIndex > 0) {
            goToQuestion(testState.currentQuestionIndex - 1);
        }
    };

    // ─── Select Option (Auto-Save) ──────────────────────────────────────────

    window.selectOption = async function (optionKey) {
        var q = testState.questions[testState.currentQuestionIndex];
        if (!q) return;

        testState.answers[q.question_id] = optionKey;
        renderCurrentQuestion();
        renderQuestionSidebar();

        // Auto-save to backend
        try {
            await testApiFetch('/api/test/save-answer', {
                method: 'POST',
                body: JSON.stringify({
                    attempt_id: testState.currentAttemptId,
                    question_id: q.question_id,
                    selected_option: optionKey
                })
            });
        } catch (err) {
            console.error('Failed to save answer:', err);
        }
    };

    // ─── Timer ──────────────────────────────────────────────────────────────

    function startTimer() {
        if (testState.timerInterval) {
            clearInterval(testState.timerInterval);
        }

        updateTimerDisplay();
        testState.timerInterval = setInterval(function () {
            var remaining = getRemainingSeconds();
            if (remaining <= 0) {
                clearInterval(testState.timerInterval);
                autoSubmitTest();
            } else {
                updateTimerDisplay();
            }
        }, 1000);
    }

    function getRemainingSeconds() {
        var startMs = new Date(testState.startTime + 'Z').getTime();
        var endMs = startMs + (testState.durationMinutes * 60 * 1000);
        var nowMs = Date.now();
        return Math.max(0, Math.floor((endMs - nowMs) / 1000));
    }

    function updateTimerDisplay() {
        var remaining = getRemainingSeconds();
        var minutes = Math.floor(remaining / 60);
        var seconds = remaining % 60;
        var display = padZero(minutes) + ':' + padZero(seconds);

        var timerEl = document.getElementById('test-timer-value');
        var timerContainer = document.getElementById('test-timer-container');

        if (timerEl) timerEl.textContent = display;

        // Warning state when less than 5 minutes
        if (timerContainer) {
            if (remaining <= 300) {
                timerContainer.classList.add('warning');
            } else {
                timerContainer.classList.remove('warning');
            }
        }
    }

    function padZero(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    // ─── Submit ─────────────────────────────────────────────────────────────

    window.showSubmitConfirm = function () {
        var modal = document.getElementById('test-submit-modal');
        if (!modal) return;

        var answeredCount = Object.keys(testState.answers).length;
        var totalCount = testState.questions.length;
        var unanswered = totalCount - answeredCount;

        var infoEl = document.getElementById('test-submit-info');
        if (infoEl) {
            infoEl.textContent = 'You have answered ' + answeredCount + ' out of ' + totalCount + ' questions.' +
                (unanswered > 0 ? ' ' + unanswered + ' question(s) are unanswered.' : '');
        }

        modal.classList.remove('hidden');
    };

    window.hideSubmitConfirm = function () {
        var modal = document.getElementById('test-submit-modal');
        if (modal) modal.classList.add('hidden');
    };

    window.confirmSubmitTest = async function () {
        hideSubmitConfirm();
        await submitTest('SUBMITTED');
    };

    async function autoSubmitTest() {
        await submitTest('AUTO_SUBMITTED');
    }

    async function submitTest(type) {
        if (testState.isSubmitting) return;
        testState.isSubmitting = true;

        // Stop timer
        if (testState.timerInterval) {
            clearInterval(testState.timerInterval);
            testState.timerInterval = null;
        }

        try {
            var result = await testApiFetch('/api/test/submit', {
                method: 'POST',
                body: JSON.stringify({ attempt_id: testState.currentAttemptId })
            });

            showTestResult(result);
        } catch (err) {
            alert('Failed to submit test: ' + err.message);
            testState.isSubmitting = false;
            // Restart timer so auto-submit protection is not lost
            if (testState.currentAttemptId && getRemainingSeconds() > 0) {
                startTimer();
            }
        }
    }

    // ─── View Result ────────────────────────────────────────────────────────

    window.viewTestResult = async function (attemptId) {
        try {
            var result = await testApiFetch('/api/test/result/' + attemptId);
            showTestResult(result);
        } catch (err) {
            alert('Failed to load result: ' + err.message);
        }
    };

    function showTestResult(result) {
        // Stop timer if running
        if (testState.timerInterval) {
            clearInterval(testState.timerInterval);
            testState.timerInterval = null;
        }

        // Hide all app pages first to prevent overlapping UI
        document.querySelectorAll('.app-page').forEach(function (p) { p.classList.add('hidden'); });

        var testInterface = document.getElementById('app-test-interface');
        var testResult = document.getElementById('app-test-result');

        if (testInterface) testInterface.classList.add('hidden');
        if (testResult) testResult.classList.remove('hidden');

        // Populate result
        var scoreEl = document.getElementById('test-result-score-value');
        var percentEl = document.getElementById('test-result-percent-value');
        var statusEl = document.getElementById('test-result-status-badge');
        var correctEl = document.getElementById('test-result-correct');
        var wrongEl = document.getElementById('test-result-wrong');
        var unansweredEl = document.getElementById('test-result-unanswered');
        var totalEl = document.getElementById('test-result-total');
        var titleEl = document.getElementById('test-result-title');

        if (scoreEl) scoreEl.textContent = result.score + '/' + result.total_questions;
        if (percentEl) percentEl.textContent = result.percentage + '%';

        if (titleEl) titleEl.textContent = result.test_title || 'Test Result';

        var passed = result.percentage >= 40;
        if (statusEl) {
            statusEl.className = 'test-result-status ' + (passed ? 'passed' : 'failed');
            statusEl.innerHTML = '<span class="material-symbols-outlined">' + (passed ? 'check_circle' : 'cancel') + '</span>' +
                (passed ? 'Passed' : 'Needs Improvement');
        }

        if (correctEl) correctEl.textContent = result.correct_answers;
        if (wrongEl) wrongEl.textContent = result.wrong_answers;
        if (unansweredEl) unansweredEl.textContent = result.unanswered;
        if (totalEl) totalEl.textContent = result.total_questions;

        // Reset state
        testState.isSubmitting = false;
        testState.currentAttemptId = null;
        testState.currentTestId = null;
        testState.questions = [];
        testState.answers = {};
    }

    // Exposed so app.js can stop the timer when user navigates away from test
    window.stopTestTimer = function () {
        if (testState.timerInterval) {
            clearInterval(testState.timerInterval);
            testState.timerInterval = null;
        }
    };

    window.backToTestList = function () {
        var testListSection = document.getElementById('app-test');
        var testInterface = document.getElementById('app-test-interface');
        var testResult = document.getElementById('app-test-result');

        if (testInterface) testInterface.classList.add('hidden');
        if (testResult) testResult.classList.add('hidden');
        if (testListSection) testListSection.classList.remove('hidden');

        loadTestList();
    };

    // ─── Utility ────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})();
