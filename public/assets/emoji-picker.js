(function () {
  const emojiList = [
    '😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😍','😘','😗','😙','😚','🙂','🤗','🤔','😐','😑',
    '😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','😴','😌','🤓','😛','😜','😝','🤤','😒',
    '😓','😔','😕','🙃','🤑','😲','☹️','🙁','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩',
    '😬','😰','😱','😳','🤪','😵','😡','😠','🤬','😷','🤒','🤕','🤢','🤧','😇','🤠','🤡','🤥',
    '❤️','💔','💕','💞','💓','💗','💖','💘','💝','👍','👎','👏','🙌','🤝','👋','🙏','💪','🔥','✨','🎉',
    '✅','❌','⚡','🌟','🌙','☀️','⭐','🍎','🍉','🍔','🍕','🎁','🎈','⚽','🏆','📌','📎','✉️','📷','🎧'
  ];

  const basePath = (window.SELO_CONFIG?.basePath || '').replace(/\/$/, '');
  const baseUrl = `${basePath}/assets/vendor/emoji/twemoji/72x72/`;

  function toCodePoints(str) {
    const codePoints = [];
    for (const char of str) {
      codePoints.push(char.codePointAt(0).toString(16));
    }
    return codePoints.join('-');
  }

  function init(container, onSelect) {
    container.innerHTML = '';
    const grid = document.createElement('div');
    grid.className = 'emoji-grid';
    emojiList.forEach(emoji => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'emoji-item';
      button.title = emoji;
      button.setAttribute('aria-label', emoji);

      const img = document.createElement('img');
      img.alt = emoji;
      img.loading = 'lazy';
      img.decoding = 'async';
      img.src = baseUrl + toCodePoints(emoji) + '.png';
      img.addEventListener('error', () => {
        img.remove();
        button.textContent = emoji;
      }, { once: true });

      button.appendChild(img);
      button.addEventListener('click', () => onSelect(emoji));
      grid.appendChild(button);
    });
    container.appendChild(grid);
  }

  window.SeloEmojiPicker = { init };
})();
