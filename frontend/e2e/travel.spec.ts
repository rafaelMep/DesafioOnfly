import { expect, test, type Page } from '@playwright/test'

let travelerToken = ''
let adminToken = ''
const destination = `Recife · E2E ${Date.now()}`

async function authenticated(page: Page, token = travelerToken) {
  await page.addInitScript((value) => sessionStorage.setItem('onfly.access_token', value), token)
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Meus pedidos de viagem' })).toBeVisible()
  await expect(page.getByRole('button', { name: /^Notificações/ })).toBeVisible()
}

test.beforeAll(async ({ request }) => {
  for (const [email, assign] of [
    [
      'rafael@onfly.test',
      (value: string) => {
        travelerToken = value
      },
    ],
    [
      'admin@onfly.test',
      (value: string) => {
        adminToken = value
      },
    ],
  ] as const) {
    const response = await request.post('/api/auth/login', {
      data: { email, password: 'Onfly@123' },
    })
    expect(response.ok()).toBeTruthy()
    assign((await response.json()).access_token)
  }
})

test('guards private routes and explains incorrect credentials', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveURL(/\/login$/)
  await page.getByLabel('E-mail', { exact: true }).fill('invalid@example.com')
  await page.getByLabel('Senha', { exact: true }).fill('invalid')
  await page.getByRole('button', { name: 'Entrar na minha conta' }).click()
  await expect(page.getByRole('alert')).toContainText('E-mail ou senha inválidos')
})

test('traveler creates a request, admin approves it, and requester receives an email', async ({
  page,
  browser,
  request,
}) => {
  await page.goto('/login')
  await page.getByRole('button', { name: 'Sou viajante' }).click()
  await page.getByRole('button', { name: 'Entrar na minha conta' }).click()
  await expect(page.getByRole('heading', { name: 'Meus pedidos de viagem' })).toBeVisible()
  await page.getByRole('button', { name: 'Novo pedido' }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Destino').fill(destination)
  await dialog.getByLabel('Data de ida').fill('2099-01-10')
  await dialog.getByLabel('Data de volta').fill('2099-01-15')
  const creation = page.waitForResponse(
    (response) =>
      response.url().endsWith('/api/travel-orders') &&
      response.request().method() === 'POST' &&
      response.status() === 201,
  )
  await dialog.getByRole('button', { name: 'Enviar pedido' }).click()
  const createdId = (await (await creation).json()).data.id as number
  await expect(page.getByRole('status').filter({ hasText: 'Pedido criado' })).toBeVisible()
  const ownRow = page.getByRole('row').filter({ hasText: destination })
  await expect(ownRow).toContainText('Solicitado')
  await expect(ownRow.getByRole('button', { name: /Aprovar pedido/ })).toHaveCount(0)
  await page.screenshot({ path: 'test-results/dashboard-desktop.png', fullPage: true })

  const adminContext = await browser.newContext()
  const adminPage = await adminContext.newPage()
  await adminPage.goto('/login')
  await adminPage.getByRole('button', { name: 'Sou administrador' }).click()
  await adminPage.getByRole('button', { name: 'Entrar na minha conta' }).click()
  const adminRow = adminPage.getByRole('row').filter({ hasText: destination })
  await adminPage.getByRole('button', { name: /^Notificações/ }).click()
  const inbox = adminPage.getByRole('dialog', { name: 'Notificações', exact: true })
  const notification = inbox.getByRole('button').filter({ hasText: destination })
  await expect(notification).toBeVisible()
  await notification.click()
  const details = adminPage.getByRole('dialog').filter({ hasText: 'DETALHES DA VIAGEM' })
  await expect(details).toContainText(destination)
  await details.getByRole('button', { name: 'Fechar', exact: true }).click()
  await adminRow.getByRole('button', { name: /Aprovar pedido/ }).click()
  await adminPage.getByRole('button', { name: 'Confirmar aprovação' }).click()
  await expect(adminRow).toContainText('Aprovado')
  await expect(adminRow.getByRole('button', { name: /Cancelar pedido/ })).toHaveCount(0)

  await page.getByRole('button', { name: 'Atualizar pedidos' }).click()
  await expect(ownRow).toContainText('Aprovado')
  await page.getByRole('button', { name: /^Notificações/ }).click()
  const travelerInbox = page.getByRole('dialog', { name: 'Notificações', exact: true })
  const decision = travelerInbox.getByRole('button').filter({ hasText: destination })
  await expect(decision).toContainText('Pedido aprovado')
  await decision.click()
  const travelerDetails = page.getByRole('dialog').filter({ hasText: 'DETALHES DA VIAGEM' })
  await expect(travelerDetails).toContainText('Aprovado')
  await travelerDetails.getByRole('button', { name: 'Fechar', exact: true }).click()
  await expect
    .poll(
      async () => {
        const mail = await request.get('http://localhost:8025/api/v1/messages')
        const data = await mail.json()
        return data.messages.some(
          (message: { Subject: string; To: { Address: string }[] }) =>
            message.Subject.includes(`#${createdId}`) &&
            message.Subject.includes('aprovado') &&
            message.To.some((recipient) => recipient.Address === 'rafael@onfly.test'),
        )
      },
      { timeout: 20000 },
    )
    .toBe(true)
  await adminContext.close()
})

test('filters, empty results and recovery from network failures', async ({ page }) => {
  await authenticated(page)
  await page.getByLabel('Buscar por destino').fill('Destino inexistente xyz999')
  await page.getByRole('button', { name: 'Buscar', exact: true }).click()
  await expect(page.getByText('Nenhuma viagem por aqui')).toBeVisible()
  await page.getByRole('button', { name: 'Limpar filtros', exact: true }).last().click()
  await expect(page.getByRole('table')).toBeVisible()
  await page.route('**/api/travel-orders?*', (route) =>
    route.fulfill({ status: 503, contentType: 'application/json', body: '{}' }),
  )
  await page.getByRole('button', { name: 'Atualizar pedidos' }).click()
  await expect(page.getByText('Não conseguimos carregar os pedidos')).toBeVisible()
  await page.unroute('**/api/travel-orders?*')
  await page.getByRole('button', { name: 'Tentar novamente', exact: true }).click()
  await expect(page.getByRole('table')).toBeVisible()
})

test('expired token returns the traveler to login', async ({ page }) => {
  await authenticated(page)
  await page.evaluate(() => sessionStorage.setItem('onfly.access_token', 'invalid'))
  await page.getByRole('button', { name: 'Atualizar pedidos' }).click()
  await expect(page).toHaveURL(/\/login$/)
})

test('mobile layout fits viewport and dialog supports keyboard escape', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await authenticated(page)
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
  await expect(page.getByRole('list', { name: 'Pedidos de viagem', exact: true })).toBeVisible()
  await expect(page.getByRole('table')).toBeHidden()
  await expect(page.locator('.mobile-order-card').first().locator('.status-badge')).toBeVisible()
  await page.getByRole('button', { name: 'Novo pedido' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await expect(page.getByRole('dialog').getByLabel('Destino')).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Novo pedido' })).toBeFocused()
  await page.screenshot({ path: 'test-results/dashboard-mobile.png', fullPage: true })
})

test('administrator cannot change their own requested orders', async ({ page, request }) => {
  const ownDestination = `Pedido próprio E2E ${Date.now()}`
  const created = await request.post('/api/travel-orders', {
    headers: { Authorization: `Bearer ${adminToken}` },
    data: { destination: ownDestination, departure_date: '2099-03-10', return_date: '2099-03-12' },
  })
  expect(created.status()).toBe(201)
  await page.addInitScript(
    (value) => sessionStorage.setItem('onfly.access_token', value),
    adminToken,
  )
  await page.goto('/')
  const ownRow = page
    .getByRole('row')
    .filter({ hasText: ownDestination })
    .filter({ hasText: 'Solicitado' })
    .first()
  await expect(ownRow).toBeVisible()
  await expect(ownRow.getByRole('button', { name: /Aprovar pedido|Cancelar pedido/ })).toHaveCount(
    0,
  )
})

test('concurrent decisions cannot overwrite a terminal status in PostgreSQL', async ({
  request,
}) => {
  const created = await request.post('/api/travel-orders', {
    headers: { Authorization: `Bearer ${travelerToken}` },
    data: {
      destination: `Concorrência E2E ${Date.now()}`,
      departure_date: '2099-02-10',
      return_date: '2099-02-12',
    },
  })
  expect(created.status()).toBe(201)
  const id = (await created.json()).data.id
  const decisions = await Promise.all(
    ['aprovado', 'cancelado'].map((status) =>
      request.patch(`/api/travel-orders/${id}/status`, {
        headers: { Authorization: `Bearer ${adminToken}` },
        data: { status },
      }),
    ),
  )
  expect(decisions.map((response) => response.status()).sort()).toEqual([200, 409])
  const winner = await decisions.find((response) => response.status() === 200)!.json()
  const persisted = await request.get(`/api/travel-orders/${id}`, {
    headers: { Authorization: `Bearer ${travelerToken}` },
  })
  expect((await persisted.json()).data.status).toBe(winner.data.status)
})

test('destination suggestions support keyboard selection and a non-blocking fallback', async ({
  page,
}) => {
  await page.route('**/api/destinations?*', (route) => {
    const query = new URL(route.request().url()).searchParams.get('q') || ''
    if (query.startsWith('Ouro')) {
      return route.fulfill({
        json: {
          data: [
            {
              id: 3146107,
              name: 'Ouro Preto',
              state: 'MG',
              country: 'Brasil',
              country_code: 'BR',
              label: 'Ouro Preto, MG, Brasil',
            },
          ],
          available: true,
          source: 'ibge',
        },
      })
    }
    return route.fulfill({
      json: query.startsWith('Lond')
        ? {
            data: [
              {
                id: 2643743,
                name: 'Londres',
                state: 'Inglaterra',
                country: 'Reino Unido',
                country_code: 'GB',
                label: 'Londres, Inglaterra, Reino Unido',
              },
            ],
            available: true,
          }
        : { data: [], available: false },
    })
  })
  await authenticated(page)
  await page.getByRole('button', { name: 'Novo pedido' }).click()
  const dialog = page.getByRole('dialog')
  const input = dialog.getByRole('combobox')
  await input.fill('Londres')
  await expect(page.getByRole('option', { name: /Londres.*Reino Unido/ })).toBeVisible()
  await input.press('ArrowDown')
  await input.press('Enter')
  await expect(input).toHaveValue('Londres, Inglaterra, Reino Unido')
  await expect(page.getByRole('listbox')).toHaveCount(0)
  await input.fill('Paris, França')
  await expect(
    page.getByRole('status').filter({ hasText: 'Sugestões indisponíveis' }),
  ).toBeVisible()
  await input.press('Escape')
  await expect(dialog).toBeVisible()
  await expect(input).toHaveValue('Paris, França')
  await expect(input).toBeEnabled()
  await input.fill('Ouro Preto')
  const fallbackOption = page.getByRole('option', { name: /Ouro Preto.*MG.*Brasil/ })
  await expect(fallbackOption).toBeVisible()
  await expect(dialog.getByRole('link', { name: 'IBGE', exact: true })).toBeVisible()
  await fallbackOption.click()
  await expect(input).toHaveValue('Ouro Preto, MG, Brasil')
})

test('logged-in admin receives a new-order badge through polling and the inbox fits mobile', async ({
  page,
  request,
}) => {
  await page.clock.install()
  await page.addInitScript(
    (value) => sessionStorage.setItem('onfly.access_token', value),
    adminToken,
  )
  const initialCount = page.waitForResponse(
    (response) => response.url().includes('/api/notifications/unread-count') && response.ok(),
  )
  await page.goto('/')
  const before = (await (await initialCount).json()).unread_count as number
  const bell = page.getByRole('button', { name: /^Notificações/ })
  await expect(bell).toBeVisible()
  const newDestination = `Recife · E2E aviso ${Date.now()}`
  const created = await request.post('/api/travel-orders', {
    headers: { Authorization: `Bearer ${travelerToken}` },
    data: { destination: newDestination, departure_date: '2099-04-10', return_date: '2099-04-12' },
  })
  expect(created.status()).toBe(201)
  await page.clock.runFor(31000)
  await expect(bell).toHaveAttribute('aria-label', `Notificações, ${before + 1} não lidas`)
  await bell.click()
  const inbox = page.getByRole('dialog', { name: 'Notificações', exact: true })
  const notice = inbox.getByRole('button').filter({ hasText: newDestination })
  await expect(notice).toBeVisible()
  await inbox.screenshot({ path: 'test-results/notifications-desktop.png' })
  await page.setViewportSize({ width: 390, height: 844 })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
  await page.screenshot({ path: 'test-results/notifications-mobile.png' })
  const marked = page.waitForResponse(
    (response) =>
      response.url().includes('/api/notifications/') && response.request().method() === 'PATCH',
  )
  await notice.click()
  const result = await (await marked).json()
  expect(result.data.read_at).not.toBeNull()
  await expect(page.getByRole('dialog').filter({ hasText: 'DETALHES DA VIAGEM' })).toContainText(
    newDestination,
  )
})

test('traveler receives a cancellation notice through polling and also receives email', async ({
  page,
  request,
}) => {
  await page.clock.install()
  await page.addInitScript(
    (value) => sessionStorage.setItem('onfly.access_token', value),
    travelerToken,
  )
  const initialCount = page.waitForResponse(
    (response) => response.url().includes('/api/notifications/unread-count') && response.ok(),
  )
  await page.goto('/')
  const before = (await (await initialCount).json()).unread_count as number
  const newDestination = `Recife · E2E cancelamento ${Date.now()}`
  const created = await request.post('/api/travel-orders', {
    headers: { Authorization: `Bearer ${travelerToken}` },
    data: { destination: newDestination, departure_date: '2099-05-10', return_date: '2099-05-12' },
  })
  expect(created.status()).toBe(201)
  const orderId = (await created.json()).data.id as number
  const decision = await request.patch(`/api/travel-orders/${orderId}/status`, {
    headers: { Authorization: `Bearer ${adminToken}` },
    data: { status: 'cancelado' },
  })
  expect(decision.status()).toBe(200)
  await page.clock.runFor(31000)
  const bell = page.getByRole('button', { name: /^Notificações/ })
  await expect(bell).toHaveAttribute('aria-label', `Notificações, ${before + 1} não lidas`)
  await bell.click()
  const inbox = page.getByRole('dialog', { name: 'Notificações', exact: true })
  const notice = inbox.getByRole('button').filter({ hasText: newDestination })
  await expect(notice).toContainText('Pedido cancelado')
  await expect(notice).toContainText('Seu pedido de viagem')
  await inbox.screenshot({ path: 'test-results/traveler-notifications-desktop.png' })
  await notice.click()
  const details = page.getByRole('dialog').filter({ hasText: 'DETALHES DA VIAGEM' })
  await expect(details.locator('.status-badge')).toHaveText('Cancelado')
  await expect
    .poll(
      async () => {
        const mail = await request.get('http://localhost:8025/api/v1/messages')
        const data = await mail.json()
        return data.messages.some(
          (message: { Subject: string; To: { Address: string }[] }) =>
            message.Subject.includes(`#${orderId}`) &&
            message.Subject.includes('cancelado') &&
            message.To.some((recipient) => recipient.Address === 'rafael@onfly.test'),
        )
      },
      { timeout: 20000 },
    )
    .toBe(true)
})
