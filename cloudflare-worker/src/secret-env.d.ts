// Wrangler generates bindings from wrangler.jsonc. Secrets are deliberately absent
// from that file, so this declaration supplements the generated Env type.
interface Env {
  ADMIN_API_KEY: string;
}
