import { SITE_TYPES } from "./siteTypes.js";

export default function CategoryPicker({ label, selected, onChange, emptyLabel }) {
  return (
    <details className="category-picker">
      <summary>{label} · {selected.length ? selected.join(", ") : emptyLabel}</summary>
      <fieldset>
        <legend>{label}</legend>
        {SITE_TYPES.map((type) => (
          <label key={type}>
            <input
              type="checkbox"
              checked={selected.includes(type)}
              onChange={(event) =>
                onChange(event.target.checked
                  ? [...selected, type]
                  : selected.filter((item) => item !== type))
              }
            />
            {type}
          </label>
        ))}
      </fieldset>
    </details>
  );
}
