// Vitest global setup for component tests.
//
// Adds jest-dom's custom matchers (toBeInTheDocument, toHaveTextContent, …) to
// `expect`. @testing-library/vue auto-cleans the rendered DOM after each test
// because Vitest runs with `globals: true` (see vite.config.ts).
import '@testing-library/jest-dom/vitest'
