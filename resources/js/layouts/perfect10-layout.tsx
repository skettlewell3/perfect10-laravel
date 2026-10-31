import type { PropsWithChildren } from 'react';

import ShellHeader from '@/components/perfect10/shell/ShellHeader';
import usePerfect10Realtime from '@/hooks/perfect10/usePerfect10Realtime';

export default function Perfect10Layout({ children }: PropsWithChildren) {
    usePerfect10Realtime();

    return (
        <div className="perfect10Root">
            <div id="appContainer">
                <ShellHeader />

                <div className="appContent">{children}</div>
            </div>
        </div>
    );
}
