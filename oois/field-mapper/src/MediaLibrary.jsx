export default function MediaLibrary({ site, onRemove }) {
  const groups = [
    { kind: "photos", title: "Photos", items: site.photos },
    { kind: "videos", title: "Videos", items: site.videos ?? [] },
  ];
  return (
    <section className="media-library">
      <h3>Site media library</h3>
      {groups.map(({ kind, title, items }) => (
        <div key={kind}>
          <h4>{title} ({items.length})</h4>
          <div className="photo-grid">
            {items.map((item) => (
              <div className="photo-thumb" key={item.id}>
                {kind === "photos" ? (
                  <img src={item.dataUrl} alt={item.name} />
                ) : (
                  <video src={item.dataUrl} controls playsInline preload="metadata" aria-label={item.name} />
                )}
                <a href={item.dataUrl} download={kind === "photos" ? item.name.replace(/\.[^.]+$/, "") + ".jpg" : item.name}>{item.name}</a>
                {onRemove && (
                  <button type="button" onClick={() => onRemove(kind, item.id)}>
                    Remove {kind === "photos" ? "photo" : "video"}
                  </button>
                )}
              </div>
            ))}
          </div>
        </div>
      ))}
    </section>
  );
}
