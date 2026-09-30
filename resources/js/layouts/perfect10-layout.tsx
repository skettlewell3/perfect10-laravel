import type { PropsWithChildren } from 'react';

export default function Perfect10Layout({ children }: PropsWithChildren) {
    return (
        <div className="perfect10Root">
            <div id="appContainer">
                <div className="appContent">{children}</div>
            </div>
        </div>
    );
}
