"""Набор тестов для Flask-приложения."""

import importlib.util
import sys
from pathlib import Path
from unittest.mock import Mock

import pytest

app_path = Path(__file__).resolve().parent.parent / "app.py"
spec = importlib.util.spec_from_file_location("autorization_app", app_path)
autorization_app = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = autorization_app
spec.loader.exec_module(autorization_app)
app = autorization_app.app

@pytest.fixture
def client():
    """Создаёт тестовый клиент Flask."""

    app.config['TESTING'] = True
    with app.test_client() as client:
        yield client

def test_login_page(client):
    """Страница логина должна успешно открываться."""

    resp = client.get('/login')
    assert resp.status_code == 200
    assert 'Вход' in resp.text

def test_successful_login(client, monkeypatch):
    """Пользователь должен попасть на рабочую страницу после входа."""

    # эмулируем успешный ответ от сервиса Моего Склада
    mock_resp = Mock()
    mock_resp.status_code = 200
    monkeypatch.setattr('requests.get', lambda *a, **k: mock_resp)
    monkeypatch.setattr(autorization_app, 'billing_allows', lambda u: True)

    resp = client.post(
        '/login',
        data={'username': 'admin', 'password': 'password'},
        follow_redirects=True,
    )
    assert b'Work Page' in resp.data


def test_protected_work_page(client):
    """Неавторизованный пользователь должен быть перенаправлен на логин."""

    resp = client.get('/work')
    # Для неавторизованного запроса ожидаем редирект (код 302)
    assert resp.status_code == 302

