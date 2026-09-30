import type { PropsWithChildren } from 'react';
import ShellHeader from '@/components/perfect10/shell/ShellHeader';

export default function Perfect10Layout({ children }: PropsWithChildren) {
    return (
        <div className="perfect10Root">
            <div id="appContainer">
                <ShellHeader />

                <div className="appContent">{children}</div>
            </div>
        </div>
    );
}
