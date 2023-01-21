document.body.classList.add(localStorage.getItem('theme'))

const button = document.createElement('a')
button.href = '#'
button.classList.add('theme-switch')
button.addEventListener('click', evt => {
    evt.preventDefault()
    const dark = document.body.classList.toggle('dark')
    if (!dark) {
        document.body.classList.toggle('light')
    }
    localStorage.setItem('theme', dark ? 'dark' : 'light')
})
button.innerHTML = '🌙️ Nite or Day Theme ☀️'
document.body.appendChild(button)
