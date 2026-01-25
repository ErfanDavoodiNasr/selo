(function () {
  const emojiList = [
    '😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😍','😘','😗','😙','😚','🙂','🤗','🤔','😐','😑',
    '😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','😴','😌','🤓','😛','😜','😝','🤤','😒',
    '😓','😔','😕','🙃','🤑','😲','☹️','🙁','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩',
    '😬','😰','😱','😳','🤪','😵','😡','😠','🤬','😷','🤒','🤕','🤢','🤧','😇','🤠','🤡','🤥',
    '❤️','💔','💕','💞','💓','💗','💖','💘','💝','👍','👎','👏','🙌','🤝','👋','🙏','💪','🔥','✨','🎉',
    '✅','❌','⚡','🌟','🌙','☀️','⭐','🍎','🍉','🍔','🍕','🎁','🎈','⚽','🏆','📌','📎','✉️','📷','🎧'
  ];

  const baseUrl = 'https://cdn.jsdelivr.net/npm/emoji-datasource-apple@6.0.1/img/apple/64/';

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
      const img = document.createElement('img');
      img.className = 'emoji-item';
      img.alt = emoji;
      img.src = baseUrl + toCodePoints(emoji) + '.png';
      img.addEventListener('click', () => onSelect(emoji));
      grid.appendChild(img);
    });
    container.appendChild(grid);
  }

  window.SeloEmojiPicker = { init };
})();
