const TONE_TO_TITLE = {
  info: 'Информация',
  success: 'Успешно',
  warning: 'Предупреждение',
  error: 'Ошибка',
};

export function createNotifier(container) {
  if (!container) {
    throw new Error('Notification container is not provided');
  }

  function push(message, { tone = 'info', title = TONE_TO_TITLE[tone], timeout = 6000 } = {}) {
    const wrapper = document.createElement('div');
    wrapper.className = `notification notification--${tone}`;

    const heading = document.createElement('strong');
    heading.textContent = title ?? TONE_TO_TITLE.info;
    const paragraph = document.createElement('p');
    paragraph.textContent = message;

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'notification__close';
    closeButton.innerHTML = '&times;';
    closeButton.setAttribute('aria-label', 'Закрыть уведомление');

    closeButton.addEventListener('click', () => {
      container.removeChild(wrapper);
    });

    wrapper.append(heading, paragraph, closeButton);
    container.appendChild(wrapper);

    if (timeout > 0) {
      setTimeout(() => {
        if (wrapper.parentElement === container) {
          container.removeChild(wrapper);
        }
      }, timeout);
    }
  }

  return {
    info(message, options) {
      push(message, { tone: 'info', ...options });
    },
    success(message, options) {
      push(message, { tone: 'success', ...options });
    },
    warning(message, options) {
      push(message, { tone: 'warning', ...options });
    },
    error(message, options) {
      push(message, { tone: 'error', ...options });
    },
  };
}
