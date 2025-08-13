/**
 * Simple Rich Text Editor for Email Composition
 */

class EmailEditor {
    constructor(textareaId, options = {}) {
        this.textarea = document.getElementById(textareaId);
        if (!this.textarea) return;
        
        this.options = {
            toolbar: options.toolbar || ['bold', 'italic', 'underline', 'highlight', 'link', 'clear'],
            highlightColors: options.highlightColors || ['#FFEB3B', '#FFC107', '#FF9800', '#4CAF50', '#03A9F4', '#E91E63'],
            ...options
        };
        
        this.init();
    }
    
    init() {
        // Create editor container
        const container = document.createElement('div');
        container.className = 'email-editor-container';
        
        // Create toolbar
        const toolbar = this.createToolbar();
        
        // Create editable div
        const editor = document.createElement('div');
        editor.className = 'email-editor-content';
        editor.contentEditable = true;
        editor.innerHTML = this.textarea.value || '';
        editor.style.minHeight = '300px';
        editor.style.border = '1px solid #ced4da';
        editor.style.borderRadius = '0.375rem';
        editor.style.padding = '0.75rem';
        editor.style.backgroundColor = '#fff';
        editor.style.overflowY = 'auto';
        editor.style.maxHeight = '500px';
        
        // Hide original textarea
        this.textarea.style.display = 'none';
        
        // Insert editor after textarea
        container.appendChild(toolbar);
        container.appendChild(editor);
        this.textarea.parentNode.insertBefore(container, this.textarea.nextSibling);
        
        // Store references
        this.editor = editor;
        this.toolbar = toolbar;
        
        // Sync content back to textarea
        editor.addEventListener('input', () => {
            this.syncToTextarea();
        });
        
        // Handle paste - clean up formatting
        editor.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        });
        
        // Update button states
        editor.addEventListener('mouseup', () => this.updateButtonStates());
        editor.addEventListener('keyup', () => this.updateButtonStates());
    }
    
    createToolbar() {
        const toolbar = document.createElement('div');
        toolbar.className = 'email-editor-toolbar';
        toolbar.style.padding = '0.5rem';
        toolbar.style.backgroundColor = '#f8f9fa';
        toolbar.style.border = '1px solid #ced4da';
        toolbar.style.borderBottom = 'none';
        toolbar.style.borderRadius = '0.375rem 0.375rem 0 0';
        toolbar.style.display = 'flex';
        toolbar.style.gap = '0.25rem';
        toolbar.style.flexWrap = 'wrap';
        
        const buttons = {
            bold: { icon: 'B', title: 'Bold (Ctrl+B)', command: 'bold' },
            italic: { icon: 'I', title: 'Italic (Ctrl+I)', command: 'italic', style: 'font-style: italic' },
            underline: { icon: 'U', title: 'Underline (Ctrl+U)', command: 'underline', style: 'text-decoration: underline' },
            highlight: { icon: '🖍️', title: 'Highlight', command: 'highlight' },
            link: { icon: '🔗', title: 'Insert Link', command: 'link' },
            clear: { icon: '🧹', title: 'Clear Formatting', command: 'removeFormat' }
        };
        
        this.options.toolbar.forEach(btn => {
            if (buttons[btn]) {
                const button = this.createButton(buttons[btn], btn);
                toolbar.appendChild(button);
            }
        });
        
        return toolbar;
    }
    
    createButton(config, type) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary editor-btn';
        button.innerHTML = config.icon;
        button.title = config.title;
        button.dataset.command = config.command;
        button.dataset.type = type;
        
        if (config.style) {
            button.style.cssText = config.style;
        }
        
        button.style.minWidth = '32px';
        button.style.fontWeight = type === 'bold' ? 'bold' : 'normal';
        
        if (type === 'highlight') {
            // Create highlight dropdown
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.showHighlightMenu(button);
            });
        } else if (type === 'link') {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.insertLink();
            });
        } else {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                document.execCommand(config.command, false, null);
                this.editor.focus();
                this.syncToTextarea();
                this.updateButtonStates();
            });
        }
        
        return button;
    }
    
    showHighlightMenu(button) {
        // Remove any existing menu
        const existingMenu = document.querySelector('.highlight-menu');
        if (existingMenu) {
            existingMenu.remove();
            return;
        }
        
        const menu = document.createElement('div');
        menu.className = 'highlight-menu';
        menu.style.position = 'absolute';
        menu.style.backgroundColor = '#fff';
        menu.style.border = '1px solid #ced4da';
        menu.style.borderRadius = '0.375rem';
        menu.style.padding = '0.5rem';
        menu.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
        menu.style.display = 'flex';
        menu.style.gap = '0.25rem';
        menu.style.zIndex = '1000';
        
        // Add remove highlight option
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-secondary';
        removeBtn.innerHTML = '❌';
        removeBtn.title = 'Remove highlight';
        removeBtn.style.minWidth = '32px';
        removeBtn.addEventListener('click', () => {
            document.execCommand('hiliteColor', false, 'transparent');
            this.editor.focus();
            this.syncToTextarea();
            menu.remove();
        });
        menu.appendChild(removeBtn);
        
        // Add color options
        this.options.highlightColors.forEach(color => {
            const colorBtn = document.createElement('button');
            colorBtn.type = 'button';
            colorBtn.className = 'btn btn-sm';
            colorBtn.style.backgroundColor = color;
            colorBtn.style.minWidth = '32px';
            colorBtn.style.minHeight = '32px';
            colorBtn.style.border = '1px solid #ced4da';
            colorBtn.title = color;
            
            colorBtn.addEventListener('click', () => {
                document.execCommand('hiliteColor', false, color);
                this.editor.focus();
                this.syncToTextarea();
                menu.remove();
            });
            
            menu.appendChild(colorBtn);
        });
        
        // Position menu
        const rect = button.getBoundingClientRect();
        menu.style.left = rect.left + 'px';
        menu.style.top = (rect.bottom + 5) + 'px';
        
        document.body.appendChild(menu);
        
        // Close menu when clicking outside
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target) && e.target !== button) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 100);
    }
    
    insertLink() {
        const url = prompt('Enter URL:');
        if (url) {
            document.execCommand('createLink', false, url);
            this.editor.focus();
            this.syncToTextarea();
        }
    }
    
    updateButtonStates() {
        const buttons = this.toolbar.querySelectorAll('.editor-btn');
        buttons.forEach(button => {
            const command = button.dataset.command;
            if (command && command !== 'highlight' && command !== 'link') {
                if (document.queryCommandState(command)) {
                    button.classList.add('active');
                    button.classList.remove('btn-outline-secondary');
                    button.classList.add('btn-secondary');
                } else {
                    button.classList.remove('active');
                    button.classList.add('btn-outline-secondary');
                    button.classList.remove('btn-secondary');
                }
            }
        });
    }
    
    syncToTextarea() {
        // Convert the HTML to a format that preserves formatting
        let content = this.editor.innerHTML;
        
        // Convert formatting to simple HTML that can be rendered in emails
        content = content.replace(/<div>/g, '\n');
        content = content.replace(/<\/div>/g, '');
        content = content.replace(/<br>/g, '\n');
        
        this.textarea.value = content;
        
        // Trigger change event for any listeners
        const event = new Event('change', { bubbles: true });
        this.textarea.dispatchEvent(event);
    }
    
    getHtmlContent() {
        return this.editor.innerHTML;
    }
    
    getTextContent() {
        return this.editor.innerText;
    }
}

// Initialize editors when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Campaign email editor
    if (document.getElementById('email_content')) {
        new EmailEditor('email_content');
    }
    
    // Edit campaign email editor
    if (document.getElementById('edit_email_content')) {
        new EmailEditor('edit_email_content');
    }
    
    // Instant email editor
    if (document.getElementById('message')) {
        new EmailEditor('message');
    }
});