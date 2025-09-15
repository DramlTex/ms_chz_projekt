"""Основное Flask-приложение проекта.

Файл реализует простую систему авторизации и страницу
"рабочей области". Все данные хранятся в памяти, поэтому
приложение подходит лишь для демонстрационных целей.
"""

from flask import Flask, render_template, request, redirect, url_for, session
from functools import wraps
from werkzeug.middleware.proxy_fix import ProxyFix
import requests

app = Flask(__name__)
# Секретный ключ используется Flask для подписи cookies сессий.
app.secret_key = 'change_this_secret'

# ProxyFix: x_proto и x_host – чтобы Flask знал настоящий протокол/хост,
#           x_prefix        – чтобы подставлять /test во все url_for()
app.wsgi_app = ProxyFix(app.wsgi_app, x_proto=1, x_host=1, x_prefix=1)

# URL для запроса настроек пользователя "Моего Склада"
MY_SKLAD_URL = 'https://api.moysklad.ru/api/remap/1.2/context/usersettings'


def login_required(f):
    """Декоратор проверки авторизации для защищённых страниц."""

    @wraps(f)
    def decorated(*args, **kwargs):
        # Если в сессии нет пользователя, отправляем на страницу логина
        if 'user' not in session:
            return redirect(url_for('login'))
        # Иначе выполняем оригинальную функцию представления
        return f(*args, **kwargs)

    return decorated


@app.route('/')
def index():
    """Домашняя страница.

    Перенаправляет пользователя на страницу работы, если он уже
    авторизован, или на форму логина в противном случае.
    """

    if 'user' in session:
        # Пользователь уже вошёл в систему
        return redirect(url_for('work'))
    # Нет информации о пользователе — отправляем на логин
    return redirect(url_for('login'))


@app.route('/login', methods=['GET', 'POST'])
def login():
    """Страница входа в систему."""

    if request.method == 'POST':
        # Получаем данные из формы
        username = request.form.get('username')
        password = request.form.get('password')

        try:
            resp = requests.get(MY_SKLAD_URL, auth=(username, password), timeout=5)
            if resp.status_code == 200:
                session['user'] = username
                return redirect(url_for('work'))
            # Если получен ответ не 200, считаем его ошибкой авторизации
            error = 'Ошибка авторизации в "Моём Складе"'
        except requests.RequestException:
            # Сетевая ошибка при запросе к сервису
            error = 'Ошибка соединения с сервисом "Мой Склад"'

        # В случае любой ошибки выводим соответствующее сообщение
        return render_template('login.html', error=error)

    # GET-запрос отображает форму авторизации
    return render_template('login.html')


@app.route('/logout')
def logout():
    """Выход пользователя из системы."""

    session.pop('user', None)
    # После удаления пользователя из сессии возвращаем его на логин
    return redirect(url_for('login'))


@app.route('/work')
@login_required
def work():
    """Закрытая страница рабочей области."""

    return render_template('work.html')


if __name__ == '__main__':
    # Запуск приложения в режиме разработки.
    # Порт и адрес должны совпадать с настройками прокси-сервера
    # (например, Apache или nginx), если он используется.
    app.run(host='127.0.0.1', port=5003, debug=True)
