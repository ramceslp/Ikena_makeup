/**
 * Formats an ISO date string into the Spanish long form used across every
 * certificate variant, e.g. "16 de junio de 2026". Shared so the five
 * variants never drift in their date presentation.
 */
export function formatCertificateDate(iso) {
  return new Intl.DateTimeFormat('es', { dateStyle: 'long' }).format(new Date(iso))
}
