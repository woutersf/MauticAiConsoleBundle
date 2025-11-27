/**
 * Mautic AI Console JavaScript
 * Handles console overlay functionality, input processing, and UI interactions
 */

class MauticAiConsole {
    constructor() {
        this.overlay = null;
        this.input = null;
        this.output = null;
        this.form = null;
        this.microphone = null;
        this.isLoading = false;
        this.isRecording = false;
        this.isProcessingSpeech = false;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.consoleUrl = '/ai-console/process';
        this.historyUrl = '/ai-console/history';
        this.speechToTextUrl = '/ai-console/speech-to-text';
        this.speechToTextEnabled = false;
        this.isLoadingHistory = false;

        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        this.injectConsoleHTML();
        this.bindEvents();
        this.checkUrlParameter();
        this.checkFirstTimeTooltip();
    }

    injectConsoleHTML() {
        // Check if console already exists
        if (document.getElementById('ai-console-overlay')) {
            return;
        }

        // Create and inject console HTML
        const consoleHTML = `
            <!-- AI Console Overlay -->
            <div id="ai-console-overlay" class="ai-console-overlay" style="display: none;">
                <div class="ai-console-container">
                    <div class="ai-console-header">
                        <div class="ai-console-title">
                            <i class="ri-sparkling-line"></i>
                            Mautic AI Console
                        </div>
                        <button type="button" class="ai-console-close" id="ai-console-close">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                    <div class="ai-console-content">
                        <div class="ai-console-output" id="ai-console-output">
                            <div class="ai-console-welcome">
                                Welcome to Mautic AI Console ✨<br>
                                <span class="loading-text">Loading conversation history...</span>
                            </div>
                        </div>
                        <form class="ai-console-input-form" id="ai-console-form">
                            <div class="ai-console-input-container">
                                <div class="ai-console-input-wrapper">
                                    <textarea
                                        id="ai-console-input"
                                        class="ai-console-input"
                                        placeholder="Enter your AI command..."
                                        rows="1"></textarea>
                                    <div class="ai-console-microphone">
                                        <i class="ri-mic-line"></i>
                                    </div>
                                </div>
                                <button type="submit" class="ai-console-submit" id="ai-console-submit">
                                    <i class="ri-send-plane-line"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', consoleHTML);

        // Get references to DOM elements
        this.overlay = document.getElementById('ai-console-overlay');
        this.input = document.getElementById('ai-console-input');
        this.output = document.getElementById('ai-console-output');
        this.form = document.getElementById('ai-console-form');
        this.microphone = document.querySelector('.ai-console-microphone');

        // Check if speech-to-text is enabled via server-side config
        this.checkSpeechToTextConfig();
    }

    bindEvents() {
        // Toggle button click
        document.addEventListener('click', (e) => {
            if (e.target.closest('.ai-console-toggle')) {
                e.preventDefault();
                this.toggle();
            }
        });

        // Close button click
        document.addEventListener('click', (e) => {
            if (e.target.closest('.ai-console-close')) {
                this.hide();
            }
        });

        // Click outside to close
        document.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.hide();
            }
        });

        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isVisible()) {
                this.hide();
            }
        });

        // § key to toggle console (typical console key on keyboards)
        document.addEventListener('keydown', (e) => {
            if (e.key === '§') {
                e.preventDefault();
                this.toggle();
            }
        });

        // Form submission
        if (this.form) {
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.processInput();
            });
        }

        // Microphone click
        if (this.microphone) {
            this.microphone.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleSpeechToText();
            });
        }

        // Auto-resize textarea
        if (this.input) {
            this.input.addEventListener('input', () => {
                this.autoResizeTextarea();
            });

            // Enter to submit (Shift+Enter for new line)
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (!this.isLoading) {
                        this.processInput();
                    }
                }
            });
        }
    }

    toggle() {
        if (this.isVisible()) {
            this.hide();
        } else {
            this.show();
        }
    }

    show() {
        if (!this.overlay) return;

        this.overlay.style.display = 'flex';
        // Small delay to trigger CSS transition
        setTimeout(() => {
            this.overlay.classList.add('show');
        }, 10);

        // Focus input
        if (this.input) {
            setTimeout(() => {
                this.input.focus();
            }, 300);
        }

        document.body.style.overflow = 'hidden';

        // Load conversation history
        this.loadConversationHistory();

        // Add console parameter to URL
        this.addConsoleToUrl();
    }

    hide() {
        if (!this.overlay) return;

        this.overlay.classList.add('slide-down');
        this.overlay.classList.remove('show');

        setTimeout(() => {
            this.overlay.style.display = 'none';
            // Reset the animation class for next time
            this.overlay.classList.remove('slide-down');
        }, 300);

        document.body.style.overflow = '';

        // Remove console parameter from URL
        this.removeConsoleFromUrl();
    }

    isVisible() {
        return this.overlay && this.overlay.style.display !== 'none';
    }

    async processInput() {
        if (!this.input || !this.output || this.isLoading) return;

        const inputValue = this.input.value.trim();
        if (!inputValue) return;

        // Add user message to output
        this.addMessage(inputValue, 'user', true);

        // Clear input and show loading
        this.input.value = '';
        this.autoResizeTextarea();
        this.setLoading(true);

        // Add AI response message with thinking indicator
        const aiMessageDiv = this.addMessage('AI is thinking...', 'ai thinking', true);

        try {
            // Get CSRF token
            const csrfToken = mQuery('meta[name="csrf-token"]').attr('content') ||
                             mQuery('input[name="mauticform[_token]"]').val() ||
                             mQuery('input[name="_token"]').val();

            // Send request to backend for streaming
            const formData = new URLSearchParams({
                input: inputValue
            });

            // Add CSRF token if available
            if (csrfToken) {
                formData.append('_token', csrfToken);
            }

            const response = await fetch(this.consoleUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            if (!response.ok) {
                throw new Error('Failed to communicate with AI service');
            }

            // Handle JSON response
            const data = await response.json();

            if (data.success) {
                aiMessageDiv.innerHTML = data.response;
                aiMessageDiv.className = aiMessageDiv.className.replace('thinking', '');
                this.output.scrollTop = this.output.scrollHeight;
            } else {
                aiMessageDiv.innerHTML = 'Error: ' + (data.error || 'Unknown error');
                aiMessageDiv.className = aiMessageDiv.className.replace('ai thinking', 'error');
            }

            this.setLoading(false);

            // Restore focus to input after response
            if (this.input) {
                this.input.focus();
            }

        } catch (error) {
            this.setLoading(false);
            if (aiMessageDiv) {
                aiMessageDiv.innerHTML = 'Error: ' + error.message;
                aiMessageDiv.className = aiMessageDiv.className.replace('ai thinking', 'error');
            } else {
                this.addMessage('Error: ' + error.message, 'error', true);
            }

            // Restore focus to input after error
            if (this.input) {
                this.input.focus();
            }
        }
    }

    addMessage(text, type = 'ai', animate = true) {
        if (!this.output) return null;

        const messageDiv = document.createElement('div');
        messageDiv.className = `ai-console-message ${type}${animate ? ' new' : ''}`;
        messageDiv.innerHTML = text;

        this.output.appendChild(messageDiv);
        this.output.scrollTop = this.output.scrollHeight;

        // Remove 'new' class after animation only if animating
        if (animate) {
            setTimeout(() => {
                messageDiv.classList.remove('new');
            }, 300);
        }

        return messageDiv;
    }

    setLoading(loading) {
        this.isLoading = loading;
        const submitBtn = document.getElementById('ai-console-submit');
        if (submitBtn) {
            submitBtn.disabled = loading;
        }
        if (this.input) {
            this.input.disabled = loading;
        }
    }

    autoResizeTextarea() {
        if (!this.input) return;

        this.input.style.height = 'auto';
        this.input.style.height = Math.min(this.input.scrollHeight, 120) + 'px';
    }

    checkUrlParameter() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('console') === 'true') {
            this.show();
        }
    }

    addConsoleToUrl() {
        const url = new URL(window.location);
        url.searchParams.set('console', 'true');
        window.history.pushState({}, '', url);
    }

    removeConsoleFromUrl() {
        const url = new URL(window.location);
        url.searchParams.delete('console');
        window.history.pushState({}, '', url);
    }

    async loadConversationHistory() {
        if (this.isLoadingHistory || !this.output) return;

        this.isLoadingHistory = true;

        try {
            // Get CSRF token
            const csrfToken = mQuery('meta[name="csrf-token"]').attr('content') ||
                             mQuery('input[name="mauticform[_token]"]').val() ||
                             mQuery('input[name="_token"]').val();

            const formData = new URLSearchParams();

            // Add CSRF token if available
            if (csrfToken) {
                formData.append('_token', csrfToken);
            }

            const response = await fetch(this.historyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            if (!response.ok) {
                if (response.status === 500) {
                    throw new Error('Server error loading conversation history');
                } else {
                    throw new Error('Failed to load conversation history');
                }
            }

            const data = await response.json();

            if (data.success && data.history) {
                if (data.history.length > 0) {
                    // Clear welcome message
                    const welcomeMsg = this.output.querySelector('.ai-console-welcome');
                    if (welcomeMsg) {
                        welcomeMsg.remove();
                    }

                    // Add historical messages
                    data.history.forEach(entry => {
                        // Add user message
                        this.addMessage(entry.prompt, 'user', false);
                        // Add AI response
                        this.addMessage(entry.output, 'ai', false);
                    });
                } else {
                    // No history - show standard welcome message
                    this.updateWelcomeMessage('Type your question to get started (or type \\help)');
                }

                this.output.scrollTop = this.output.scrollHeight;
            } else {
                this.updateWelcomeMessage('Type your commands below...');
            }

        } catch (error) {
            console.error('Error loading conversation history:', error);
            this.showHistoryError(error.message);
        } finally {
            this.isLoadingHistory = false;
        }
    }

    updateWelcomeMessage(message) {
        const welcomeMsg = this.output.querySelector('.ai-console-welcome .loading-text');
        if (welcomeMsg) {
            welcomeMsg.textContent = message;
        }
    }

    showHistoryError(errorMessage) {
        // Clear welcome message and show error
        const welcomeMsg = this.output.querySelector('.ai-console-welcome');
        if (welcomeMsg) {
            welcomeMsg.remove();
        }

        // Add error message
        this.addMessage('Error loading conversation history: ' + errorMessage, 'error', false);

        // Add a new welcome message
        const welcomeDiv = document.createElement('div');
        welcomeDiv.className = 'ai-console-welcome';
        welcomeDiv.innerHTML = 'Welcome to Mautic AI Console ✨<br>Type your question to get started (or type \\help)';
        this.output.appendChild(welcomeDiv);

        this.output.scrollTop = this.output.scrollHeight;
    }

    async checkSpeechToTextConfig() {
        try {
            // Try both URL formats to see which one works
            let response;
            try {
                response = await fetch('/ai-console/speech-to-text', {
                    method: 'HEAD',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (response.ok) {
                    this.speechToTextUrl = '/ai-console/speech-to-text';
                }
            } catch (e) {
                // Try without /s/ prefix
                response = await fetch('/ai-console/speech-to-text', {
                    method: 'HEAD',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (response.ok) {
                    this.speechToTextUrl = '/ai-console/speech-to-text';
                }
            }

            // If endpoint is available and returns success, enable speech-to-text
            this.speechToTextEnabled = response.ok;
            console.log('Speech-to-text enabled:', this.speechToTextEnabled, 'URL:', this.speechToTextUrl);
        } catch (error) {
            console.error('Speech-to-text config check failed:', error);
            this.speechToTextEnabled = false;
        }

        // Hide or show microphone based on config
        if (this.microphone) {
            if (this.speechToTextEnabled) {
                this.microphone.classList.remove('hidden');
                console.log('Microphone shown');
            } else {
                this.microphone.classList.add('hidden');
                console.log('Microphone hidden');
            }
        }
    }

    async toggleSpeechToText() {
        if (!this.speechToTextEnabled) {
            return;
        }

        if (this.isRecording) {
            this.stopRecording();
        } else {
            await this.startRecording();
        }
    }

    async startRecording() {
        if (this.isRecording || this.isProcessingSpeech) {
            return;
        }

        try {
            // Request microphone access
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            this.isRecording = true;
            this.audioChunks = [];

            // Set recording visual state
            this.microphone.classList.add('recording');
            this.microphone.querySelector('i').className = 'ri-mic-fill';

            // Create MediaRecorder
            this.mediaRecorder = new MediaRecorder(stream);

            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.audioChunks.push(event.data);
                }
            };

            this.mediaRecorder.onstop = () => {
                this.processSpeechToText();
                // Stop all tracks to release microphone
                stream.getTracks().forEach(track => track.stop());
            };

            // Start recording
            this.mediaRecorder.start();

        } catch (error) {
            console.error('Error starting recording:', error);
            this.showError('Failed to access microphone. Please check permissions.');
            this.resetMicrophoneState();
        }
    }

    stopRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.isRecording = false;
            this.mediaRecorder.stop();

            // Set processing visual state
            this.microphone.classList.remove('recording');
            this.microphone.classList.add('processing');
            this.microphone.querySelector('i').className = 'ri-loader-line';
        }
    }

    async processSpeechToText() {
        if (!this.audioChunks.length) {
            this.resetMicrophoneState();
            return;
        }

        try {
            this.isProcessingSpeech = true;

            // Create audio blob
            const audioBlob = new Blob(this.audioChunks, { type: 'audio/wav' });

            // Create form data
            const formData = new FormData();
            formData.append('audio', audioBlob, 'recording.wav');

            // Send to speech-to-text endpoint
            const response = await fetch(this.speechToTextUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.text) {
                // Insert transcribed text into input
                this.input.value = data.text;
                this.autoResizeTextarea();
                this.input.focus();
            } else {
                throw new Error(data.error || 'Failed to transcribe audio');
            }

        } catch (error) {
            console.error('Error processing speech to text:', error);
            this.showError('Failed to process speech: ' + error.message);
        } finally {
            this.isProcessingSpeech = false;
            this.resetMicrophoneState();
        }
    }

    resetMicrophoneState() {
        if (this.microphone) {
            this.microphone.classList.remove('recording', 'processing');
            this.microphone.querySelector('i').className = 'ri-mic-line';
        }
        this.isRecording = false;
        this.isProcessingSpeech = false;
    }

    showError(message) {
        this.addMessage('Error: ' + message, 'error');
    }

    checkFirstTimeTooltip() {
        // Check if user has already seen the tooltip
        const hasSeenTooltip = localStorage.getItem('mautic-ai-console-tooltip-seen');
        console.log('hasSeenTooltip', hasSeenTooltip);

        if (!hasSeenTooltip) {
            // Wait a bit for the page to fully load, then show tooltip
            setTimeout(() => {
                this.showFirstTimeTooltip();
            }, 1000);
        }
    }

    showFirstTimeTooltip() {
        const consoleButton = document.querySelector('#ai-console-toggle');
        if (!consoleButton) {
            return;
        }

        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'ai-console-first-time-tooltip';
        tooltip.innerHTML = `
            <button class="tooltip-close" aria-label="Close">&times;</button>
            <div class="tooltip-content">
                <span class="tooltip-emoji">></span>
                <span>Need help? Type your questions here</span>
            </div>
        `;

        // Position tooltip below the button
        const buttonRect = consoleButton.getBoundingClientRect();
        tooltip.style.left = (buttonRect.left + buttonRect.width / 2 - 140) + 'px';
        tooltip.style.top = (buttonRect.bottom + 15) + 'px';

        // Add tooltip to page
        document.body.appendChild(tooltip);

        // Show tooltip with animation and activate button
        setTimeout(() => {
            tooltip.classList.add('show');
            consoleButton.classList.add('tooltip-active'); // Add custom active state to button
        }, 100);

        // Auto-hide after 5 seconds
        const autoHideTimer = setTimeout(() => {
            this.hideFirstTimeTooltip(tooltip);
        }, 30000);

        // Close button functionality
        const closeBtn = tooltip.querySelector('.tooltip-close');
        closeBtn.addEventListener('click', () => {
            clearTimeout(autoHideTimer);
            this.hideFirstTimeTooltip(tooltip);
        });

        // Close on click outside
        const outsideClickHandler = (event) => {
            if (!tooltip.contains(event.target)) {
                clearTimeout(autoHideTimer);
                document.removeEventListener('click', outsideClickHandler);
                this.hideFirstTimeTooltip(tooltip);
            }
        };

        setTimeout(() => {
            document.addEventListener('click', outsideClickHandler);
        }, 200);
    }

    hideFirstTimeTooltip(tooltip) {
        tooltip.classList.remove('show');

        // Remove active state from button
        const consoleButton = document.querySelector('#ai-console-toggle');
        if (consoleButton) {
            consoleButton.classList.remove('tooltip-active');
        }

        // Remove tooltip after animation completes
        setTimeout(() => {
            if (tooltip.parentNode) {
                tooltip.parentNode.removeChild(tooltip);
            }
        }, 300);

        // Mark as seen so it doesn't show again
        localStorage.setItem('mautic-ai-console-tooltip-seen', 'true');
    }
}

// Initialize console when script loads
window.MauticAiConsole = new MauticAiConsole();