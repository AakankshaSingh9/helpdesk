// Shape of the `meta` object Laravel's paginated API resources return.
export type PageMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}
