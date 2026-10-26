type Props = {
    venue: string;
    metaHeader: string;
    className?: string;
};

export default function FixtureCellHeader({
    venue,
    metaHeader,
    className = '',
}: Props) {
    return (
        <div className={`fixtureCellHeader ${className}`}>
            <div className="resultHeader">{venue}</div>

            <div className="metaHeader">{metaHeader}</div>
        </div>
    );
}
