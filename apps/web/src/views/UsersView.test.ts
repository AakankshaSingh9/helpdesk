import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/vue'
import UsersView from './UsersView.vue'
import { api, ApiError } from '../lib/api'

// Mock the API module. We re-implement ApiError (a real class) so the
// component's `e instanceof ApiError` branch behaves like production, and
// replace `api` with a spy each test controls.
vi.mock('../lib/api', () => {
  class ApiError extends Error {
    status: number
    errors?: Record<string, string[]>
    constructor(status: number, message: string, errors?: Record<string, string[]>) {
      super(message)
      this.status = status
      this.errors = errors
    }
  }
  return { api: vi.fn(), ApiError, ensureCsrfCookie: vi.fn() }
})

const mockedApi = vi.mocked(api)

const users = [
  {
    id: '1',
    name: 'Ada Lovelace',
    email: 'ada@helpdesk.test',
    role: 'admin',
    emailVerified: true,
    createdAt: '2026-01-01T00:00:00+00:00',
  },
  {
    id: '2',
    name: 'Grace Hopper',
    email: 'grace@helpdesk.test',
    role: 'agent',
    emailVerified: false,
    createdAt: '2026-02-02T00:00:00+00:00',
  },
]

// RouterLink needs a router; stub it to a plain anchor that renders its slot so
// we can mount the view in isolation.
function renderView() {
  return render(UsersView, {
    global: {
      stubs: { RouterLink: { template: '<a><slot /></a>' } },
    },
  })
}

beforeEach(() => {
  mockedApi.mockReset()
})

describe('UsersView', () => {
  it('shows a loading state until the request resolves', () => {
    mockedApi.mockReturnValue(new Promise(() => {})) // never resolves
    renderView()

    expect(screen.getByText('Loading users…')).toBeInTheDocument()
  })

  it('requests /api/users on mount and renders a row per user', async () => {
    mockedApi.mockResolvedValue({ data: users })
    renderView()

    expect(await screen.findByText('Ada Lovelace')).toBeInTheDocument()
    expect(screen.getByText('Grace Hopper')).toBeInTheDocument()
    expect(screen.getByText('ada@helpdesk.test')).toBeInTheDocument()

    expect(mockedApi).toHaveBeenCalledWith('/api/users')
    // Header reflects the count.
    expect(screen.getByText('2 users.')).toBeInTheDocument()
  })

  it('badges admins and shows the plain role for everyone else', async () => {
    mockedApi.mockResolvedValue({ data: users })
    renderView()
    await screen.findByText('Ada Lovelace')

    const adminRow = screen.getByText('Ada Lovelace').closest('tr') as HTMLElement
    expect(within(adminRow).getByText('Admin')).toBeInTheDocument()

    const agentRow = screen.getByText('Grace Hopper').closest('tr') as HTMLElement
    expect(within(agentRow).getByText('agent')).toBeInTheDocument()
  })

  it('renders an empty state when there are no users', async () => {
    mockedApi.mockResolvedValue({ data: [] })
    renderView()

    expect(await screen.findByText('No users match your search.')).toBeInTheDocument()
  })

  it('shows an error and recovers when Retry succeeds', async () => {
    mockedApi.mockRejectedValueOnce(new ApiError(500, 'Server exploded'))
    renderView()

    expect(await screen.findByRole('alert')).toHaveTextContent('Server exploded')

    mockedApi.mockResolvedValueOnce({ data: users })
    await fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    expect(await screen.findByText('Ada Lovelace')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('debounces search and passes the term to the API', async () => {
    vi.useFakeTimers()
    try {
      mockedApi.mockResolvedValue({ data: users })
      const { container } = renderView()
      await vi.advanceTimersByTimeAsync(0) // flush the on-mount load

      expect(mockedApi).toHaveBeenCalledTimes(1)
      expect(mockedApi).toHaveBeenLastCalledWith('/api/users')

      const input = container.querySelector('input[type="search"]') as HTMLInputElement
      await fireEvent.update(input, 'ada')

      // Still debounced — no request yet.
      expect(mockedApi).toHaveBeenCalledTimes(1)

      // After the 250ms debounce window it fires with the search term.
      await vi.advanceTimersByTimeAsync(250)
      expect(mockedApi).toHaveBeenLastCalledWith('/api/users?search=ada')
    } finally {
      vi.useRealTimers()
    }
  })
})
