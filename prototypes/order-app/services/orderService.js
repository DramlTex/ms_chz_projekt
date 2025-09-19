import { wait } from '../utils/wait.js';
import { formatDisplayDateTime } from '../utils/datetime.js';

/**
 * Имитация отправки заказа КМ. Возвращает идентификатор заказа и таймлайн.
 * @param {{ selectedCards: Array<object>; payload: { scenario: string; packaging: string; deadline?: Date | null; comment?: string; quantities: Record<string, number>; }; auth?: { bearerToken?: string; tokenExpiresAt?: Date | null; certificate?: { subject?: string; thumbprint?: string } } }} params
 */
export async function submitOrder(params) {
  const { selectedCards, payload, auth = {} } = params;
  const bearerToken = auth.bearerToken?.replace(/^Bearer\s+/i, '').trim();
  if (!bearerToken) {
    throw new Error('Отсутствует bearer-токен True API. Выполните подпись перед отправкой заказа.');
  }
  await wait(900);

  const orderId = generateOrderId();
  const now = new Date();
  const totalQuantity = Object.values(payload.quantities ?? {}).reduce(
    (acc, value) => acc + (Number.isFinite(value) ? value : 0),
    0,
  );

  const workflow = buildWorkflow(now, payload.scenario);

  return {
    orderId,
    createdAt: now,
    scenario: payload.scenario,
    packaging: payload.packaging,
    deadline: payload.deadline ?? null,
    comment: payload.comment ?? '',
    totalCards: selectedCards.length,
    totalQuantity,
    workflow,
    authorization: {
      tokenPreview: maskSecret(bearerToken),
      tokenExpiresAt: auth.tokenExpiresAt ?? null,
      certificateSubject: auth.certificate?.subject ?? null,
      certificateThumbprint: auth.certificate?.thumbprint ?? null,
    },
  };
}

function generateOrderId() {
  const random = Math.floor(100000 + Math.random() * 900000);
  return `ORD-${random}`;
}

function maskSecret(value) {
  if (typeof value !== 'string' || value.length === 0) {
    return '';
  }
  const trimmed = value.trim();
  if (trimmed.length <= 6) {
    return '••••••';
  }
  return `••••${trimmed.slice(-6)}`;
}

function buildWorkflow(startDate, scenario) {
  const steps = [
    {
      title: 'Заявка сформирована',
      description: 'Список карточек преобразован в пакет заказа КМ.',
      timestamp: startDate,
    },
    {
      title: 'Отправлено в True API',
      description: 'Данные переданы в True API для регистрации заказа.',
      timestamp: new Date(startDate.getTime() + 60000),
    },
    {
      title: 'СУЗ подтвердил приём',
      description: scenario === 'import'
        ? 'Получено подтверждение импорта. Ожидайте выпуск кодов.'
        : 'Заказ принят. Коды направлены на печать.',
      timestamp: new Date(startDate.getTime() + 120000),
    },
    {
      title: 'Заказ готов к закрытию',
      description: 'Можно выполнить выбытие или передачу кодов маркировки.',
      timestamp: new Date(startDate.getTime() + 180000),
    },
  ];

  return steps.map((step) => ({
    ...step,
    formatted: formatDisplayDateTime(step.timestamp),
  }));
}
